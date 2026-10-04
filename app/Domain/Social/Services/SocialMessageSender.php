<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Social\Data\OutboundSocialMessageResult;
use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Exceptions\SocialMessageDeliveryException;
use App\Domain\Social\Models\SocialMessage;

final readonly class SocialMessageSender
{
    public function __construct(
        private MetaMessengerApi $meta,
        private TelegramBotApi $telegram,
    ) {}

    public function send(SocialMessage $message): OutboundSocialMessageResult
    {
        $message->loadMissing('conversation.channel', 'conversation.contact');
        $conversation = $message->conversation;
        $channel = $conversation?->channel;
        $contact = $conversation?->contact;

        if ($channel === null || $contact === null || blank($message->body)) {
            throw new SocialMessageDeliveryException('The outbound message is missing its channel, contact, or text.');
        }
        if (! $channel->active) {
            throw new SocialMessageDeliveryException('The social channel is inactive.');
        }

        return match ($channel->platform) {
            SocialPlatform::FacebookMessenger => $this->meta->sendText($contact->external_id, $message->body),
            SocialPlatform::Telegram => $this->telegram->sendText($contact->external_id, $message->body),
        };
    }
}
