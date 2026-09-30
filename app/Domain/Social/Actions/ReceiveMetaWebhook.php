<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Adapters\MetaMessengerAdapter;
use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Enums\SocialWebhookStatus;
use App\Domain\Social\Exceptions\InvalidSocialWebhookException;
use App\Domain\Social\Models\SocialWebhookEvent;
use App\Jobs\ProcessMetaWebhook;
use JsonException;

final class ReceiveMetaWebhook
{
    public function __construct(private readonly MetaMessengerAdapter $adapter) {}

    /** @return array{event: SocialWebhookEvent, duplicate: bool} */
    public function execute(string $rawPayload, ?string $signature): array
    {
        if (! $this->adapter->signatureIsValid($rawPayload, $signature)) {
            throw new InvalidSocialWebhookException('The Meta webhook signature is invalid.');
        }

        try {
            $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidSocialWebhookException('The Meta webhook payload is not valid JSON.', previous: $exception);
        }

        if (! is_array($payload) || ($payload['object'] ?? null) !== 'page') {
            throw new InvalidSocialWebhookException('The Meta webhook payload is not a Page event.');
        }

        $eventId = hash('sha256', $rawPayload);
        $event = SocialWebhookEvent::query()->firstOrCreate(
            ['event_id' => $eventId],
            [
                'platform' => SocialPlatform::FacebookMessenger,
                'status' => SocialWebhookStatus::Received,
                'payload' => $payload,
                'received_at' => now(),
            ],
        );

        if (! $event->wasRecentlyCreated) {
            return ['event' => $event, 'duplicate' => true];
        }

        ProcessMetaWebhook::dispatch($event->id)->onQueue('integrations');

        return ['event' => $event, 'duplicate' => false];
    }
}
