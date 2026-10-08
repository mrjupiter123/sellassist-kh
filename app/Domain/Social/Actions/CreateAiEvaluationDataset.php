<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Enums\AiEvaluationDatasetStatus;
use App\Domain\Social\Exceptions\SocialOrderExtractionException;
use App\Domain\Social\Models\SocialAiEvaluationCase;
use App\Domain\Social\Models\SocialAiEvaluationDataset;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateAiEvaluationDataset
{
    /** @param list<int> $caseIds */
    public function execute(
        string $name,
        string $version,
        string $releaseNotes,
        array $caseIds,
        User $actor,
    ): SocialAiEvaluationDataset {
        return DB::transaction(function () use ($name, $version, $releaseNotes, $caseIds, $actor): SocialAiEvaluationDataset {
            $cases = SocialAiEvaluationCase::query()
                ->where('active', true)
                ->whereIn('id', $caseIds)
                ->orderBy('id')
                ->get(['id']);

            if ($cases->count() !== count(array_unique($caseIds))) {
                throw new SocialOrderExtractionException('Every dataset case must be an active synthetic evaluation case.');
            }

            $dataset = SocialAiEvaluationDataset::query()->create([
                'name' => $name,
                'version' => $version,
                'release_notes' => $releaseNotes,
                'status' => AiEvaluationDatasetStatus::Frozen,
                'created_by' => $actor->id,
                'frozen_by' => $actor->id,
                'frozen_at' => now(),
            ]);

            $dataset->cases()->attach($cases->values()->mapWithKeys(
                fn (SocialAiEvaluationCase $case, int $position): array => [$case->id => ['position' => $position + 1]],
            )->all());

            return $dataset;
        });
    }
}
