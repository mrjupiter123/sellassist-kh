<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Enums\AiEvaluationResultStatus;
use App\Domain\Social\Enums\AiEvaluationRunStatus;
use App\Domain\Social\Exceptions\SocialOrderExtractionException;
use App\Domain\Social\Models\SocialAiEvaluationRun;
use App\Domain\Social\Models\SocialAiExtractionProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ApproveAiExtractionProfile
{
    public function execute(SocialAiExtractionProfile $profile, SocialAiEvaluationRun $run, User $actor): SocialAiExtractionProfile
    {
        return DB::transaction(function () use ($profile, $run, $actor): SocialAiExtractionProfile {
            $lockedProfile = SocialAiExtractionProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $lockedRun = SocialAiEvaluationRun::query()->lockForUpdate()->findOrFail($run->id);
            $threshold = (float) config('social.ai.evaluation_approval_threshold', 0.85);

            if ($lockedRun->social_ai_extraction_profile_id !== $lockedProfile->id
                || $lockedRun->status !== AiEvaluationRunStatus::Completed
                || $lockedRun->score === null
                || (float) $lockedRun->score < $threshold
                || $lockedRun->total_cases < 1
                || $lockedRun->results()->count() !== $lockedRun->total_cases
                || $lockedRun->results()->where('status', AiEvaluationResultStatus::Error->value)->exists()) {
                throw new SocialOrderExtractionException('This evaluation run does not satisfy the profile approval gate.');
            }

            $lockedProfile->update([
                'activation_eligible' => true,
                'approved_by' => $actor->id,
                'approval_evaluation_run_id' => $lockedRun->id,
                'approved_at' => now(),
            ]);

            return $lockedProfile;
        });
    }
}
