<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Enums\AiEvaluationResultStatus;
use App\Domain\Social\Enums\AiEvaluationRunStatus;
use App\Domain\Social\Exceptions\SocialOrderExtractionException;
use App\Domain\Social\Models\SocialAiEvaluationRun;
use App\Domain\Social\Models\SocialAiExtractionProfile;
use App\Domain\Social\Services\AiEvaluationComparisonService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ApproveAiExtractionProfile
{
    public function __construct(private readonly AiEvaluationComparisonService $comparisonService) {}

    public function execute(
        SocialAiExtractionProfile $profile,
        SocialAiEvaluationRun $run,
        User $actor,
        string $releaseNotes,
    ): SocialAiExtractionProfile {
        return DB::transaction(function () use ($profile, $run, $actor, $releaseNotes): SocialAiExtractionProfile {
            $lockedProfile = SocialAiExtractionProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $lockedRun = SocialAiEvaluationRun::query()->lockForUpdate()->findOrFail($run->id);
            $threshold = (float) config('social.ai.evaluation_approval_threshold', 0.85);

            if ($lockedRun->social_ai_extraction_profile_id !== $lockedProfile->id
                || $lockedRun->status !== AiEvaluationRunStatus::Completed
                || $lockedRun->score === null
                || $lockedRun->social_ai_evaluation_dataset_id === null
                || (float) $lockedRun->score < $threshold
                || $lockedRun->total_cases < 1
                || $lockedRun->results()->count() !== $lockedRun->total_cases
                || $lockedRun->results()->where('status', AiEvaluationResultStatus::Error->value)->exists()) {
                throw new SocialOrderExtractionException('This evaluation run does not satisfy the profile approval gate.');
            }

            $activeProfile = SocialAiExtractionProfile::query()
                ->where('active', true)
                ->where('id', '!=', $lockedProfile->id)
                ->with('approvalRun')
                ->first();
            if ($activeProfile && ! $activeProfile->approvalRun) {
                throw new SocialOrderExtractionException(
                    'Evaluate and approve the active profile on this frozen dataset before approving a replacement.',
                );
            }

            $baseline = $activeProfile?->approvalRun;
            $comparison = null;

            if ($baseline) {
                $comparison = $this->comparisonService->compare($baseline, $lockedRun);
                if ($comparison['has_regression']) {
                    throw new SocialOrderExtractionException(
                        'Approval blocked because this profile regresses against the active profile on the same dataset.',
                    );
                }
            }

            $lockedProfile->update([
                'activation_eligible' => true,
                'approved_by' => $actor->id,
                'approval_evaluation_run_id' => $lockedRun->id,
                'approval_baseline_run_id' => $baseline?->id,
                'approval_score_delta' => $comparison['score_delta'] ?? null,
                'approval_release_notes' => $releaseNotes,
                'approved_at' => now(),
            ]);

            return $lockedProfile;
        });
    }
}
