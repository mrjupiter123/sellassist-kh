<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Models\SocialAiExtractionProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateAiExtractionProfile
{
    /** @param array{name: string, version: string, model: string, instructions: string} $data */
    public function execute(array $data, User $actor): SocialAiExtractionProfile
    {
        return DB::transaction(function () use ($data, $actor): SocialAiExtractionProfile {
            return SocialAiExtractionProfile::query()->create([
                'name' => trim($data['name']),
                'version' => trim($data['version']),
                'model' => trim($data['model']),
                'instructions' => trim($data['instructions']),
                'active' => false,
                'activation_eligible' => false,
                'created_by' => $actor->id,
            ]);
        });
    }
}
