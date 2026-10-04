<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Enums\ConversationStatus;
use App\Domain\Social\Exceptions\SocialConversationException;
use App\Domain\Social\Models\SocialConversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ChangeSocialConversationStatus
{
    public function execute(
        SocialConversation $conversation,
        ConversationStatus $requestedStatus,
        User $actor,
    ): SocialConversation {
        return DB::transaction(function () use ($conversation, $requestedStatus, $actor): SocialConversation {
            $locked = SocialConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $target = $this->target($locked, $requestedStatus);
            if ($target === $locked->status) {
                return $locked;
            }

            $locked->update([
                'status' => $target,
                'archived_at' => $target === ConversationStatus::Archived ? now() : null,
                'archived_by' => $target === ConversationStatus::Archived ? $actor->id : null,
            ]);

            return $locked->refresh();
        });
    }

    private function target(SocialConversation $conversation, ConversationStatus $requestedStatus): ConversationStatus
    {
        if ($requestedStatus === ConversationStatus::Archived
            && in_array($conversation->status, [ConversationStatus::Open, ConversationStatus::Converted], true)) {
            return ConversationStatus::Archived;
        }

        if ($requestedStatus === ConversationStatus::Open && $conversation->status === ConversationStatus::Archived) {
            return $conversation->converted_order_id === null
                ? ConversationStatus::Open
                : ConversationStatus::Converted;
        }

        if ($requestedStatus === $conversation->status) {
            return $conversation->status;
        }

        throw new SocialConversationException(
            "Cannot change conversation from {$conversation->status->label()} to {$requestedStatus->label()}.",
        );
    }
}
