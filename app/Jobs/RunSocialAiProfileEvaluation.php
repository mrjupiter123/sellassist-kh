<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Social\Enums\AiEvaluationResultStatus;
use App\Domain\Social\Enums\AiEvaluationRunStatus;
use App\Domain\Social\Exceptions\SocialOrderExtractionException;
use App\Domain\Social\Models\SocialAiEvaluationCase;
use App\Domain\Social\Models\SocialAiEvaluationRun;
use App\Domain\Social\Services\AiExtractionEvaluationScorer;
use App\Domain\Social\Services\OpenAiSocialOrderExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RunSocialAiProfileEvaluation implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [15, 60, 120];

    public function __construct(public readonly int $runId) {}

    public function uniqueId(): string
    {
        return (string) $this->runId;
    }

    public function handle(OpenAiSocialOrderExtractor $extractor, AiExtractionEvaluationScorer $scorer): void
    {
        $run = SocialAiEvaluationRun::query()->with('profile')->findOrFail($this->runId);
        if ($run->status === AiEvaluationRunStatus::Completed) {
            return;
        }
        $run->update(['status' => AiEvaluationRunStatus::Running, 'started_at' => $run->started_at ?? now(), 'error' => null]);

        try {
            $cases = SocialAiEvaluationCase::query()->whereIn('id', array_map('intval', $run->case_ids))->orderBy('id')->get();
            foreach ($cases as $case) {
                $result = $extractor->extractSynthetic($run->profile, $case->messages, $case->catalog);
                $score = $scorer->score($case->expected_result, $result->payload);
                $run->results()->updateOrCreate(
                    ['social_ai_evaluation_case_id' => $case->id],
                    [
                        'status' => $score['passed'] ? AiEvaluationResultStatus::Passed : AiEvaluationResultStatus::Failed,
                        'score' => $score['score'],
                        'passed' => $score['passed'],
                        'actual_result' => $result->payload,
                        'differences' => $score['differences'],
                        'provider_response_id' => $result->responseId,
                        'total_tokens' => $result->totalTokens ?? 0,
                        'error' => null,
                        'processed_at' => now(),
                    ],
                );
            }

            DB::transaction(function (): void {
                $locked = SocialAiEvaluationRun::query()->lockForUpdate()->findOrFail($this->runId);
                $results = $locked->results()->get();
                $locked->update([
                    'status' => AiEvaluationRunStatus::Completed,
                    'total_cases' => $results->count(),
                    'passed_cases' => $results->where('passed', true)->count(),
                    'failed_cases' => $results->where('passed', false)->count(),
                    'score' => $results->isEmpty() ? 0 : round((float) $results->avg('score'), 4),
                    'total_tokens' => (int) $results->sum('total_tokens'),
                    'completed_at' => now(),
                    'error' => null,
                ]);
            });
        } catch (Throwable $exception) {
            $safeMessage = $exception instanceof SocialOrderExtractionException
                ? $exception->getMessage()
                : 'The AI profile evaluation failed unexpectedly.';
            SocialAiEvaluationRun::query()->whereKey($this->runId)->update([
                'status' => AiEvaluationRunStatus::Failed,
                'error' => mb_substr($safeMessage, 0, 1000),
                'completed_at' => now(),
            ]);
            report($exception);

            throw new SocialOrderExtractionException($safeMessage, previous: $exception);
        }
    }
}
