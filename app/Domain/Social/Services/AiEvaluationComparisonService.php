<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Social\Enums\AiEvaluationRunStatus;
use App\Domain\Social\Exceptions\SocialOrderExtractionException;
use App\Domain\Social\Models\SocialAiEvaluationRun;

final class AiEvaluationComparisonService
{
    /**
     * @return array{
     *   baseline: SocialAiEvaluationRun,
     *   candidate: SocialAiEvaluationRun,
     *   score_delta: float,
     *   pass_rate_delta: float,
     *   token_delta: int,
     *   has_regression: bool,
     *   regressions: list<string>,
     *   cases: list<array{name: string, baseline_score: float, candidate_score: float, delta: float, regressed: bool}>
     * }
     */
    public function compare(SocialAiEvaluationRun $baseline, SocialAiEvaluationRun $candidate): array
    {
        if ($baseline->status !== AiEvaluationRunStatus::Completed || $candidate->status !== AiEvaluationRunStatus::Completed) {
            throw new SocialOrderExtractionException('Only completed evaluation runs can be compared.');
        }

        if ($baseline->social_ai_evaluation_dataset_id === null
            || $baseline->social_ai_evaluation_dataset_id !== $candidate->social_ai_evaluation_dataset_id) {
            throw new SocialOrderExtractionException('Profile comparisons must use the same frozen dataset version.');
        }

        $baseline->loadMissing(['profile', 'dataset', 'results.evaluationCase']);
        $candidate->loadMissing(['profile', 'dataset', 'results.evaluationCase']);
        $candidateResults = $candidate->results->keyBy('social_ai_evaluation_case_id');
        $tolerance = (float) config('social.ai.evaluation_regression_tolerance', 0.02);
        $regressions = [];
        $cases = [];

        foreach ($baseline->results as $baselineResult) {
            $candidateResult = $candidateResults->get($baselineResult->social_ai_evaluation_case_id);
            $baselineScore = (float) ($baselineResult->score ?? 0);
            $candidateScore = (float) ($candidateResult?->score ?? 0);
            $delta = round($candidateScore - $baselineScore, 4);
            $regressed = $candidateResult === null
                || ($baselineResult->passed && ! $candidateResult->passed)
                || $delta < -$tolerance;
            $caseName = $baselineResult->evaluationCase?->name ?? 'Deleted case';

            if ($regressed) {
                $regressions[] = $caseName;
            }
            $cases[] = [
                'name' => $caseName,
                'baseline_score' => $baselineScore,
                'candidate_score' => $candidateScore,
                'delta' => $delta,
                'regressed' => $regressed,
            ];
        }

        $scoreDelta = round((float) $candidate->score - (float) $baseline->score, 4);
        $baselinePassRate = $baseline->total_cases > 0 ? $baseline->passed_cases / $baseline->total_cases : 0;
        $candidatePassRate = $candidate->total_cases > 0 ? $candidate->passed_cases / $candidate->total_cases : 0;

        return [
            'baseline' => $baseline,
            'candidate' => $candidate,
            'score_delta' => $scoreDelta,
            'pass_rate_delta' => round($candidatePassRate - $baselinePassRate, 4),
            'token_delta' => $candidate->total_tokens - $baseline->total_tokens,
            'has_regression' => $scoreDelta < -$tolerance || $regressions !== [],
            'regressions' => $regressions,
            'cases' => $cases,
        ];
    }
}
