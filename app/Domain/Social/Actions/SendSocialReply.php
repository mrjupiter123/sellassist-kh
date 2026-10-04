<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Enums\MessageDirection;
use App\Domain\Social\Enums\MessageType;
use App\Domain\Social\Enums\SocialMessageDeliveryStatus;
use App\Domain\Social\Exceptions\SocialConversationException;
use App\Domain\Social\Models\SocialConversation;
use App\Domain\Social\Models\SocialMessage;
use App\Jobs\SendSocialMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SendSocialReply
{
    public function execute(SocialConversation $conversation, string $body, User $actor): SocialMessage
    {
        return DB::transaction(function () use ($conversation, $body, $actor): SocialMessage {
            $locked = SocialConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            if (! $locked->status->allowsReplies()) {
                throw new SocialConversationException('Archived conversations cannot receive replies.');
            }
            $locked->loadMissing('channel');
            if ($locked->channel === null || ! $locked->channel->active) {
                throw new SocialConversationException('This social channel is inactive and cannot send replies.');
            }

            $message = SocialMessage::query()->create([
                'social_conversation_id' => $locked->id,
                'external_id' => 'local:'.Str::uuid(),
                'direction' => MessageDirection::Outbound,
                'delivery_status' => SocialMessageDeliveryStatus::Pending,
                'type' => MessageType::Text,
                'body' => trim($body),
                'sent_at' => now(),
                'created_by' => $actor->id,
            ]);

            SendSocialMessage::dispatch($message->id)->onQueue('integrations')->afterCommit();

            return $message;
        });
    }
}
