<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Social\Enums\OrderExtractionReviewVerdict;
use App\Domain\Social\Enums\OrderExtractionStatus;
use App\Domain\Social\Models\SocialAiProfileRelease;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class PostReleaseMonitoringService
{
    /**
     * @return array{
     *   release: SocialAiProfileRelease,
     *   baseline: array<string, int|float|null>,
     *   candidate: array<string, int|float|null>,
     *   baseline_from: CarbonImmutable,
     *   baseline_until: CarbonImmutable,
     *   candidate_from: CarbonImmutable,
     *   candidate_until: CarbonImmutable,
     *   status: string,
     *   recommendation: string,
     *   reasons: list<string>,
     *   deltas: array{success_rate: float, average_confidence: ?float, useful_rate: ?float},
     *   minimum_samples: int,
     *   minimum_reviews: int
     * }
     */
    public function summarize(SocialAiProfileRelease $release): array
    {
        $release->loadMissing(['profile', 'previousProfile', 'approvalRun', 'releaser']);
        $windowDays = max(1, (int) config('social.ai.release_monitoring.window_days', 14));
        $minimumSamples = max(1, (int) config('social.ai.release_monitoring.minimum_samples', 10));
        $minimumReviews = max(1, (int) config('social.ai.release_monitoring.minimum_reviews', 5));
        $releasedAt = CarbonImmutable::instance($release->released_at);
        $nextReleaseAt = SocialAiProfileRelease::query()
            ->where('released_at', '>', $release->released_at)
            ->oldest('released_at')
            ->value('released_at');
        $candidateUntil = collect([
            $releasedAt->addDays($windowDays),
            CarbonImmutable::now(),
            $nextReleaseAt ? CarbonImmutable::parse($nextReleaseAt) : null,
        ])->filter()->sort()->first();
        $baselineFrom = $releasedAt->subDays($windowDays);

        $baseline = $release->previous_profile_id
            ? $this->metrics($release->previous_profile_id, $baselineFrom, $releasedAt)
            : $this->emptyMetrics();
        $candidate = $this->metrics($release->social_ai_extraction_profile_id, $releasedAt, $candidateUntil);
        $deltas = [
            'success_rate' => round((float) $candidate['success_rate'] - (float) $baseline['success_rate'], 4),
            'average_confidence' => $this->nullableDelta($candidate['average_confidence'], $baseline['average_confidence']),
            'useful_rate' => $this->nullableDelta($candidate['useful_rate'], $baseline['useful_rate']),
        ];
        $reasons = [];
        $status = 'healthy';
        $recommendation = 'No rollback is recommended from the available aggregate signals.';

        if (! $release->previous_profile_id) {
            $status = 'no_baseline';
            $recommendation = 'This is the first managed release, so there is no previous-profile baseline to compare.';
        } elseif ($baseline['samples'] < $minimumSamples || $candidate['samples'] < $minimumSamples) {
            $status = 'collecting';
            $recommendation = 'Keep monitoring. Both periods need the minimum completed extraction sample before assessment.';
        } else {
            $successDrop = (float) config('social.ai.release_monitoring.success_rate_drop', 0.10);
            $confidenceDrop = (float) config('social.ai.release_monitoring.confidence_drop', 0.10);
            $usefulDrop = (float) config('social.ai.release_monitoring.useful_rate_drop', 0.15);

            if ($deltas['success_rate'] < -$successDrop) {
                $reasons[] = 'Extraction success rate decreased beyond the configured threshold.';
            }
            if ($deltas['average_confidence'] !== null && $deltas['average_confidence'] < -$confidenceDrop) {
                $reasons[] = 'Average ready-result confidence decreased beyond the configured threshold.';
            }
            if ($baseline['reviewed'] >= $minimumReviews
                && $candidate['reviewed'] >= $minimumReviews
                && $deltas['useful_rate'] !== null
                && $deltas['useful_rate'] < -$usefulDrop) {
                $reasons[] = 'Seller-reviewed usefulness decreased beyond the configured threshold.';
            }

            if ($reasons !== []) {
                $status = 'degraded';
                $recommendation = 'Manual rollback review is recommended. Inspect the aggregate signals and evaluation evidence before deciding.';
            }
        }

        return [
            'release' => $release,
            'baseline' => $baseline,
            'candidate' => $candidate,
            'baseline_from' => $baselineFrom,
            'baseline_until' => $releasedAt,
            'candidate_from' => $releasedAt,
            'candidate_until' => $candidateUntil,
            'status' => $status,
            'recommendation' => $recommendation,
            'reasons' => $reasons,
            'deltas' => $deltas,
            'minimum_samples' => $minimumSamples,
            'minimum_reviews' => $minimumReviews,
        ];
    }

    /** @return array<string, int|float|null> */
    private function metrics(int $profileId, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $row = DB::table('social_order_extractions as extraction')
            ->leftJoin('social_order_extraction_reviews as review', 'review.social_order_extraction_id', '=', 'extraction.id')
            ->where('extraction.social_ai_extraction_profile_id', $profileId)
            ->where('extraction.created_at', '>=', $from)
            ->where('extraction.created_at', '<', $until)
            ->selectRaw('SUM(CASE WHEN extraction.status IN (?, ?) THEN 1 ELSE 0 END) AS samples', [OrderExtractionStatus::Ready->value, OrderExtractionStatus::Failed->value])
            ->selectRaw('SUM(CASE WHEN extraction.status = ? THEN 1 ELSE 0 END) AS ready', [OrderExtractionStatus::Ready->value])
            ->selectRaw('SUM(CASE WHEN extraction.status = ? THEN 1 ELSE 0 END) AS failed', [OrderExtractionStatus::Failed->value])
            ->selectRaw('AVG(CASE WHEN extraction.status = ? THEN extraction.overall_confidence END) AS average_confidence', [OrderExtractionStatus::Ready->value])
            ->selectRaw('COALESCE(SUM(extraction.total_tokens), 0) AS total_tokens')
            ->selectRaw('COUNT(review.id) AS reviewed')
            ->selectRaw('SUM(CASE WHEN review.verdict IN (?, ?) THEN 1 ELSE 0 END) AS useful', [OrderExtractionReviewVerdict::Accepted->value, OrderExtractionReviewVerdict::Corrected->value])
            ->first();
        $samples = (int) ($row->samples ?? 0);
        $ready = (int) ($row->ready ?? 0);
        $reviewed = (int) ($row->reviewed ?? 0);

        return [
            'samples' => $samples,
            'ready' => $ready,
            'failed' => (int) ($row->failed ?? 0),
            'success_rate' => $samples === 0 ? 0.0 : round($ready / $samples, 4),
            'average_confidence' => $row->average_confidence === null ? null : (float) $row->average_confidence,
            'reviewed' => $reviewed,
            'useful_rate' => $reviewed === 0 ? null : round((int) ($row->useful ?? 0) / $reviewed, 4),
            'total_tokens' => (int) ($row->total_tokens ?? 0),
            'average_tokens' => $samples === 0 ? 0.0 : round((int) ($row->total_tokens ?? 0) / $samples, 1),
        ];
    }

    /** @return array<string, int|float|null> */
    private function emptyMetrics(): array
    {
        return [
            'samples' => 0, 'ready' => 0, 'failed' => 0, 'success_rate' => 0.0,
            'average_confidence' => null, 'reviewed' => 0, 'useful_rate' => null,
            'total_tokens' => 0, 'average_tokens' => 0.0,
        ];
    }

    private function nullableDelta(int|float|null $candidate, int|float|null $baseline): ?float
    {
        return $candidate === null || $baseline === null ? null : round((float) $candidate - (float) $baseline, 4);
    }
}
