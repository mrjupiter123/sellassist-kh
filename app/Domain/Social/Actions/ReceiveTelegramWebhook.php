<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Adapters\TelegramAdapter;
use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Enums\SocialWebhookStatus;
use App\Domain\Social\Exceptions\InvalidSocialWebhookException;
use App\Domain\Social\Models\SocialChannel;
use App\Domain\Social\Models\SocialWebhookEvent;
use App\Jobs\ProcessTelegramWebhook;
use JsonException;

final class ReceiveTelegramWebhook
{
    public function __construct(private readonly TelegramAdapter $adapter) {}

    /** @return array{event: SocialWebhookEvent, duplicate: bool} */
    public function execute(SocialChannel $channel, string $rawPayload, ?string $secret): array
    {
        if ($channel->platform !== SocialPlatform::Telegram || ! $channel->active) {
            throw new InvalidSocialWebhookException('The Telegram channel is unavailable.');
        }
        if (! $this->adapter->signatureIsValid($rawPayload, $secret)) {
            throw new InvalidSocialWebhookException('The Telegram webhook secret is invalid.');
        }

        try {
            $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidSocialWebhookException('The Telegram webhook payload is not valid JSON.', previous: $exception);
        }
        if (! is_array($payload) || ! isset($payload['update_id'])) {
            throw new InvalidSocialWebhookException('The Telegram webhook payload has no update identifier.');
        }

        $eventId = hash('sha256', 'telegram:'.$channel->id.':'.(string) $payload['update_id']);
        $event = SocialWebhookEvent::query()->firstOrCreate(
            ['event_id' => $eventId],
            [
                'social_channel_id' => $channel->id,
                'platform' => SocialPlatform::Telegram,
                'status' => SocialWebhookStatus::Received,
                'payload' => $payload,
                'received_at' => now(),
            ],
        );

        if (! $event->wasRecentlyCreated) {
            return ['event' => $event, 'duplicate' => true];
        }

        ProcessTelegramWebhook::dispatch($event->id)->onQueue('integrations');

        return ['event' => $event, 'duplicate' => false];
    }
}
