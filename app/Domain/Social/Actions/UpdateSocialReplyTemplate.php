<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Models\SocialReplyTemplate;
use App\Models\User;

final class UpdateSocialReplyTemplate
{
    /** @param array<string, mixed> $data */
    public function execute(SocialReplyTemplate $template, array $data, User $actor): SocialReplyTemplate
    {
        $template->update([...$data, 'updated_by' => $actor->id]);

        return $template->refresh();
    }
}
