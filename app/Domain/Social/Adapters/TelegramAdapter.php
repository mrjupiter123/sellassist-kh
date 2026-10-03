<?php

declare(strict_types=1);

namespace App\Domain\Social\Adapters;

use App\Domain\Social\Contracts\SocialProviderAdapter;
use App\Domain\Social\Data\InboundSocialMessage;
use App\Domain\Social\Enums\MessageType;
use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Models\SocialChannel;
use Carbon\CarbonImmutable;

final class TelegramAdapter implements SocialProviderAdapter
{
    public function signatureIsValid(string $rawPayload, ?string $signature): bool
    {
        $expected = (string) config('social.telegram.webhook_secret');

        return $expected !== ''
            && $signature !== null
            && hash_equals($expected, $signature);
    }

    public function messages(array $payload, ?SocialChannel $channel = null): array
    {
        $message = $payload['message'] ?? null;
        if (! is_array($message) || $channel === null || $channel->platform !== SocialPlatform::Telegram) {
            return [];
        }

        if (data_get($message, 'chat.type') !== 'private') {
            return [];
        }

        $contactId = (string) data_get($message, 'from.id', '');
        $messageId = (string) ($message['message_id'] ?? '');
        if ($contactId === '' || $messageId === '') {
            return [];
        }

        $attachments = $this->attachments($message);
        $body = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        if (isset($message['contact']['phone_number'])) {
            $body = trim($body."\nShared contact: ".trim((string) $message['contact']['phone_number']));
        }

        return [new InboundSocialMessage(
            platform: SocialPlatform::Telegram,
            channelExternalId: $channel->external_id,
            contactExternalId: $contactId,
            messageExternalId: 'telegram:'.$contactId.':'.$messageId,
            contactDisplayName: $this->displayName((array) ($message['from'] ?? [])),
            type: $body !== '' ? MessageType::Text : ($attachments !== [] ? MessageType::Attachment : MessageType::Unsupported),
            body: $body !== '' ? $body : null,
            attachments: $attachments,
            sentAt: CarbonImmutable::createFromTimestampUTC((int) ($message['date'] ?? now()->timestamp)),
            rawPayload: $message,
        )];
    }

    /** @param array<string, mixed> $sender */
    private function displayName(array $sender): ?string
    {
        $name = trim(implode(' ', array_filter([
            isset($sender['first_name']) ? (string) $sender['first_name'] : null,
            isset($sender['last_name']) ? (string) $sender['last_name'] : null,
        ])));
        $username = isset($sender['username']) ? '@'.ltrim((string) $sender['username'], '@') : null;
        $displayName = trim($name.($username !== null ? ' ('.$username.')' : ''));

        return $displayName !== '' ? $displayName : $username;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return list<array<string, mixed>>
     */
    private function attachments(array $message): array
    {
        $attachments = [];
        foreach (['document', 'audio', 'video', 'voice', 'animation', 'sticker', 'contact', 'location'] as $type) {
            if (isset($message[$type]) && is_array($message[$type])) {
                $attachments[] = ['type' => $type, 'data' => $message[$type]];
            }
        }
        if (isset($message['photo']) && is_array($message['photo'])) {
            $attachments[] = ['type' => 'photo', 'data' => $message['photo']];
        }

        return $attachments;
    }
}
