<?php

declare(strict_types=1);

namespace App\Domain\Social\Adapters;

use App\Domain\Social\Contracts\SocialProviderAdapter;
use App\Domain\Social\Data\InboundSocialMessage;
use App\Domain\Social\Enums\MessageType;
use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Models\SocialChannel;
use Carbon\CarbonImmutable;

final class MetaMessengerAdapter implements SocialProviderAdapter
{
    public function signatureIsValid(string $rawPayload, ?string $signature): bool
    {
        $secret = (string) config('social.meta.app_secret');
        $provided = preg_replace('/^sha256=/i', '', (string) $signature);

        return $secret !== ''
            && is_string($provided)
            && $provided !== ''
            && hash_equals(hash_hmac('sha256', $rawPayload, $secret), $provided);
    }

    public function messages(array $payload, ?SocialChannel $channel = null): array
    {
        if (($payload['object'] ?? null) !== 'page') {
            return [];
        }

        $messages = [];

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['messaging'] ?? []) as $messaging) {
                $message = $messaging['message'] ?? null;
                if (! is_array($message) || ($message['is_echo'] ?? false) === true) {
                    continue;
                }

                $channelId = (string) data_get($messaging, 'recipient.id', '');
                $contactId = (string) data_get($messaging, 'sender.id', '');
                if ($channelId === '' || $contactId === '') {
                    continue;
                }

                $attachments = array_values(array_filter(
                    (array) ($message['attachments'] ?? []),
                    fn (mixed $attachment): bool => is_array($attachment),
                ));
                $body = isset($message['text']) ? trim((string) $message['text']) : null;
                $externalId = (string) ($message['mid'] ?? '');
                if ($externalId === '') {
                    $externalId = 'meta-'.hash('sha256', json_encode($messaging, JSON_THROW_ON_ERROR));
                }

                $messages[] = new InboundSocialMessage(
                    platform: SocialPlatform::FacebookMessenger,
                    channelExternalId: $channelId,
                    contactExternalId: $contactId,
                    messageExternalId: $externalId,
                    contactDisplayName: null,
                    type: $body !== null && $body !== ''
                        ? MessageType::Text
                        : ($attachments !== [] ? MessageType::Attachment : MessageType::Unsupported),
                    body: $body !== '' ? $body : null,
                    attachments: $attachments,
                    sentAt: CarbonImmutable::createFromTimestampMs((int) ($messaging['timestamp'] ?? now()->getTimestampMs())),
                    rawPayload: $messaging,
                );
            }
        }

        return $messages;
    }
}
