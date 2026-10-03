<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Data\InboundSocialMessage;
use App\Domain\Social\Enums\ConversationStatus;
use App\Domain\Social\Enums\MessageDirection;
use App\Domain\Social\Models\SocialChannel;
use App\Domain\Social\Models\SocialContact;
use App\Domain\Social\Models\SocialConversation;
use App\Domain\Social\Models\SocialMessage;
use App\Domain\Social\Services\CustomerMatchService;
use Illuminate\Support\Facades\DB;

final class IngestSocialMessage
{
    public function __construct(private readonly CustomerMatchService $customerMatcher) {}

    public function execute(InboundSocialMessage $inbound): ?SocialMessage
    {
        return DB::transaction(function () use ($inbound): ?SocialMessage {
            $existing = SocialMessage::query()->where('external_id', $inbound->messageExternalId)->first();
            if ($existing !== null) {
                return $existing;
            }

            $channel = SocialChannel::query()
                ->where('platform', $inbound->platform->value)
                ->where('external_id', $inbound->channelExternalId)
                ->where('active', true)
                ->first();
            if ($channel === null) {
                return null;
            }

            $contact = SocialContact::query()->firstOrCreate(
                ['social_channel_id' => $channel->id, 'external_id' => $inbound->contactExternalId],
                [
                    'display_name' => $inbound->contactDisplayName,
                    'first_seen_at' => $inbound->sentAt,
                    'last_seen_at' => $inbound->sentAt,
                ],
            );
            $contact->update(array_filter([
                'display_name' => $inbound->contactDisplayName,
                'last_seen_at' => $inbound->sentAt,
            ], fn (mixed $value): bool => $value !== null));

            $conversation = SocialConversation::query()
                ->where('social_contact_id', $contact->id)
                ->where('status', ConversationStatus::Open->value)
                ->lockForUpdate()
                ->latest('id')
                ->first();
            if ($conversation === null) {
                $conversation = SocialConversation::query()->create([
                    'social_channel_id' => $channel->id,
                    'social_contact_id' => $contact->id,
                    'status' => ConversationStatus::Open,
                    'last_message_at' => $inbound->sentAt,
                ]);
            }

            $message = $conversation->messages()->create([
                'external_id' => $inbound->messageExternalId,
                'direction' => MessageDirection::Inbound,
                'type' => $inbound->type,
                'body' => $inbound->body,
                'attachments' => $inbound->attachments,
                'raw_payload' => $inbound->rawPayload,
                'sent_at' => $inbound->sentAt,
            ]);
            $conversation->update(['last_message_at' => $inbound->sentAt]);

            $suggestion = $this->customerMatcher->suggest($contact, $inbound->body);
            if ($suggestion !== null && $contact->suggested_customer_id === null) {
                $contact->update(['suggested_customer_id' => $suggestion->id]);
            }

            return $message;
        });
    }
}
