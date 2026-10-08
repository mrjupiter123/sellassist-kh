<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Models\SocialAiExtractionProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateAiExtractionProfile
{
    /** @param array{name: string, version: string, model: string, instructions: string, activate?: bool} $data */
    public function execute(array $data, User $actor): SocialAiExtractionProfile
    {
        return DB::transaction(function () use ($data, $actor): SocialAiExtractionProfile {
            if ($data['activate'] ?? false) {
                SocialAiExtractionProfile::query()->where('active', true)->lockForUpdate()->update(['active' => false]);
            }

            return SocialAiExtractionProfile::query()->create([
                'name' => trim($data['name']),
                'version' => trim($data['version']),
                'model' => trim($data['model']),
                'instructions' => trim($data['instructions']),
                'active' => $data['activate'] ?? false,
                'created_by' => $actor->id,
                'activated_by' => ($data['activate'] ?? false) ? $actor->id : null,
                'activated_at' => ($data['activate'] ?? false) ? now() : null,
            ]);
        });
    }
}
