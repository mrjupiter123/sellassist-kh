<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Models\SocialReplyTemplate;
use App\Models\User;

final class CreateSocialReplyTemplate
{
    /** @param array<string, mixed> $data */
    public function execute(array $data, User $actor): SocialReplyTemplate
    {
        return SocialReplyTemplate::query()->create([
            ...$data,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }
}
