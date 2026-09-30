<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Models\SocialChannel;

final class UpdateSocialChannel
{
    /** @param array<string, mixed> $data */
    public function execute(SocialChannel $channel, array $data): SocialChannel
    {
        $channel->update($data);

        return $channel->refresh();
    }
}
