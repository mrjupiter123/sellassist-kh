<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Enums\AiEvaluationRunStatus;
use App\Domain\Social\Exceptions\SocialOrderExtractionException;
use App\Domain\Social\Models\SocialAiEvaluationCase;
use App\Domain\Social\Models\SocialAiEvaluationRun;
use App\Domain\Social\Models\SocialAiExtractionProfile;
use App\Jobs\RunSocialAiProfileEvaluation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RequestAiEvaluationRun
{
    public function execute(SocialAiExtractionProfile $profile, User $actor): SocialAiEvaluationRun
    {
        if (! config('social.ai.enabled') || blank(config('social.ai.api_key'))) {
            throw new SocialOrderExtractionException('AI evaluation is not configured.');
        }

        return DB::transaction(function () use ($profile, $actor): SocialAiEvaluationRun {
            $locked = SocialAiExtractionProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $existing = $locked->evaluationRuns()->whereIn('status', [
                AiEvaluationRunStatus::Queued,
                AiEvaluationRunStatus::Running,
            ])->latest()->first();
            if ($existing) {
                return $existing;
            }

            $caseIds = SocialAiEvaluationCase::query()->where('active', true)->orderBy('id')->pluck('id')->all();
            if ($caseIds === []) {
                throw new SocialOrderExtractionException('Create or seed at least one active synthetic evaluation case first.');
            }

            $run = $locked->evaluationRuns()->create([
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
