<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Models\SocialConversation;
use App\Models\User;

final class MarkSocialConversationUnread
{
    public function execute(SocialConversation $conversation, User $user): void
    {
        $conversation->readReceipts()->where('user_id', $user->id)->delete();
    }
}
