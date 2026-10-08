<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Models\SocialAiEvaluationCase;
use App\Models\User;

final class CreateAiEvaluationCase
{
    /**
     * @param  list<string>  $messages
     * @param  list<array<string, mixed>>  $catalog
     * @param  array<string, mixed>  $expectedResult
     */
    public function execute(
        string $name,
        string $locale,
        array $messages,
        array $catalog,
        array $expectedResult,
        User $actor,
    ): SocialAiEvaluationCase {
        return SocialAiEvaluationCase::query()->create([
            'name' => $name,
            'locale' => $locale,
            'messages' => $messages,
            'catalog' => $catalog,
            'expected_result' => $expectedResult,
            'active' => true,
            'created_by' => $actor->id,
        ]);
    }
}
