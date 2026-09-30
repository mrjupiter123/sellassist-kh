<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Models\SocialChannel;
use App\Models\User;

final class CreateSocialChannel
{
    /** @param array<string, mixed> $data */
    public function execute(array $data, User $actor): SocialChannel
    {
        return SocialChannel::query()->create([
            ...$data,
            'platform' => SocialPlatform::FacebookMessenger,
            'created_by' => $actor->id,
        ]);
    }
}
