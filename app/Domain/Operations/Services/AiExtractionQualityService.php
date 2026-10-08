<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Social\Enums\OrderExtractionReviewVerdict;
use App\Domain\Social\Enums\OrderExtractionStatus;
use App\Domain\Social\Models\SocialOrderExtractionReview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AiExtractionQualityService
{
    /**
     * @return array{
     *   period_days: int, total: int, ready: int, failed: int, pending: int,
     *   low_confidence: int, reviewed: int, accepted: int, corrected: int, rejected: int,
     *   total_tokens: int, average_confidence: ?float, success_rate: float,
     *   review_rate: float, useful_rate: float
     * }
     */
    public function summary(int $days): array
    {
        $from = $this->from($days);
        $threshold = (float) config('social.ai.low_confidence_threshold', 0.65);
        $extractions = DB::table('social_order_extractions')
            ->where('created_at', '>=', $from)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS ready', [OrderExtractionStatus::Ready->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS failed', [OrderExtractionStatus::Failed->value])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS pending', [OrderExtractionStatus::Queued->value, OrderExtractionStatus::Processing->value])
            ->selectRaw('SUM(CASE WHEN status = ? AND overall_confidence < ? THEN 1 ELSE 0 END) AS low_confidence', [OrderExtractionStatus::Ready->value, $threshold])
            ->selectRaw('COALESCE(SUM(total_tokens), 0) AS total_tokens')
            ->selectRaw('AVG(CASE WHEN status = ? THEN overall_confidence END) AS average_confidence', [OrderExtractionStatus::Ready->value])
            ->first();

        $reviews = DB::table('social_order_extraction_reviews')
            ->where('reviewed_at', '>=', $from)
            ->selectRaw('COUNT(*) AS reviewed')
            ->selectRaw('SUM(CASE WHEN verdict = ? THEN 1 ELSE 0 END) AS accepted', [OrderExtractionReviewVerdict::Accepted->value])
            ->selectRaw('SUM(CASE WHEN verdict = ? THEN 1 ELSE 0 END) AS corrected', [OrderExtractionReviewVerdict::Corrected->value])
            ->selectRaw('SUM(CASE WHEN verdict = ? THEN 1 ELSE 0 END) AS rejected', [OrderExtractionReviewVerdict::Rejected->value])
            ->first();

        $total = (int) ($extractions->total ?? 0);
        $ready = (int) ($extractions->ready ?? 0);
        $reviewed = (int) ($reviews->reviewed ?? 0);
        $accepted = (int) ($reviews->accepted ?? 0);
        $corrected = (int) ($reviews->corrected ?? 0);

        return [
            'period_days' => $days,
            'total' => $total,
            'ready' => $ready,
            'failed' => (int) ($extractions->failed ?? 0),
            'pending' => (int) ($extractions->pending ?? 0),
            'low_confidence' => (int) ($extractions->low_confidence ?? 0),
            'reviewed' => $reviewed,
            'accepted' => $accepted,
            'corrected' => $corrected,
            'rejected' => (int) ($reviews->rejected ?? 0),
            'total_tokens' => (int) ($extractions->total_tokens ?? 0),
            'average_confidence' => $extractions->average_confidence === null ? null : (float) $extractions->average_confidence,
            'success_rate' => $this->percentage($ready, $total),
            'review_rate' => $this->percentage($reviewed, $ready),
            'useful_rate' => $this->percentage($accepted + $corrected, $reviewed),
        ];
    }

    /** @return Collection<int, object> */
    public function modelBreakdown(int $days): Collection
    {
        return DB::table('social_order_extractions')
            ->where('created_at', '>=', $this->from($days))
            ->groupBy('model')
            ->orderByDesc('total')
            ->select('model')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS ready', [OrderExtractionStatus::Ready->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS failed', [OrderExtractionStatus::Failed->value])
            ->selectRaw('AVG(CASE WHEN status = ? THEN overall_confidence END) AS average_confidence', [OrderExtractionStatus::Ready->value])
            ->selectRaw('COALESCE(SUM(total_tokens), 0) AS total_tokens')
            ->get();
    }

    /** @return Collection<int, object> */
    public function dailyTrend(int $days): Collection
    {
        return DB::table('social_order_extractions')
            ->where('created_at', '>=', $this->from(min($days, 30)))
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->selectRaw('DATE(created_at) AS day')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS ready', [OrderExtractionStatus::Ready->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS failed', [OrderExtractionStatus::Failed->value])
            ->selectRaw('COALESCE(SUM(total_tokens), 0) AS total_tokens')
            ->get();
    }

    /** @return Collection<int, SocialOrderExtractionReview> */
    public function recentReviews(int $days): Collection
    {
        return SocialOrderExtractionReview::query()
            ->where('reviewed_at', '>=', $this->from($days))
            ->with([
                'reviewer:id,uuid,name',
                'extraction:id,uuid,social_conversation_id,model,overall_confidence,total_tokens,processed_at',
                'extraction.conversation:id,uuid',
            ])
            ->latest('reviewed_at')
            ->limit(10)
            ->get();
    }

    private function from(int $days): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($days - 1)->startOfDay();
    }

    private function percentage(int $numerator, int $denominator): float
    {
        return $denominator === 0 ? 0.0 : round(($numerator / $denominator) * 100, 1);
    }
}
