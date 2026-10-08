<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Enums\AiEvaluationDatasetStatus;
use App\Domain\Social\Enums\AiEvaluationRunStatus;
use App\Domain\Social\Exceptions\SocialOrderExtractionException;
use App\Domain\Social\Models\SocialAiEvaluationDataset;
use App\Domain\Social\Models\SocialAiEvaluationRun;
use App\Domain\Social\Models\SocialAiExtractionProfile;
use App\Jobs\RunSocialAiProfileEvaluation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RequestAiEvaluationRun
{
    public function execute(
        SocialAiExtractionProfile $profile,
        SocialAiEvaluationDataset $dataset,
        User $actor,
    ): SocialAiEvaluationRun {
        if (! config('social.ai.enabled') || blank(config('social.ai.api_key'))) {
            throw new SocialOrderExtractionException('AI evaluation is not configured.');
        }

        return DB::transaction(function () use ($profile, $dataset, $actor): SocialAiEvaluationRun {
            $locked = SocialAiExtractionProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $existing = $locked->evaluationRuns()->whereIn('status', [
                AiEvaluationRunStatus::Queued,
                AiEvaluationRunStatus::Running,
            ])->where('social_ai_evaluation_dataset_id', $dataset->id)->latest()->first();
            if ($existing) {
                return $existing;
            }

            $lockedDataset = SocialAiEvaluationDataset::query()->lockForUpdate()->findOrFail($dataset->id);
            if ($lockedDataset->status !== AiEvaluationDatasetStatus::Frozen) {
                throw new SocialOrderExtractionException('Only a frozen evaluation dataset can be run.');
            }

            $caseIds = $lockedDataset->cases()->pluck('social_ai_evaluation_cases.id')->map(fn ($id): int => (int) $id)->all();
            if ($caseIds === []) {
                throw new SocialOrderExtractionException('The selected evaluation dataset has no synthetic cases.');
            }

            $run = $locked->evaluationRuns()->create([
                'social_ai_evaluation_dataset_id' => $lockedDataset->id,
                'status' => AiEvaluationRunStatus::Queued,
                'case_ids' => $caseIds,
                'total_cases' => count($caseIds),
                'requested_by' => $actor->id,
            ]);
            RunSocialAiProfileEvaluation::dispatch($run->id)->onQueue('integrations')->afterCommit();

            return $run;
        });
    }
}
