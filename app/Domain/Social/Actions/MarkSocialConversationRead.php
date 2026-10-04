<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Models\SocialConversation;
use App\Domain\Social\Models\SocialConversationRead;
use App\Models\User;

final class MarkSocialConversationRead
{
    public function execute(SocialConversation $conversation, User $user): SocialConversationRead
    {
        return SocialConversationRead::query()->updateOrCreate(
            ['social_conversation_id' => $conversation->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );
    }
}
