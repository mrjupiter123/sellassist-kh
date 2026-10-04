<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Exceptions\SocialConversationException;
use App\Domain\Social\Models\SocialConversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AssignSocialConversation
{
    public function execute(SocialConversation $conversation, ?User $assignee, User $actor): SocialConversation
    {
        return DB::transaction(function () use ($conversation, $assignee, $actor): SocialConversation {
            $locked = SocialConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            if ($assignee !== null && (! $assignee->active || ! $assignee->can('social.view'))) {
                throw new SocialConversationException('Conversations can only be assigned to active users with social inbox access.');
            }

            $locked->update([
                'assigned_to' => $assignee?->id,
                'assigned_at' => $assignee !== null ? now() : null,
                'assigned_by' => $actor->id,
            ]);

            return $locked->refresh();
        });
    }
}
