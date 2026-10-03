<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Models\SocialChannel;
use App\Domain\Social\Services\TelegramBotApi;
use DomainException;

final class ConfigureTelegramBot
{
    public function __construct(private readonly TelegramBotApi $api) {}

    /** @return array{channel: SocialChannel, webhook: array<string, mixed>} */
    public function execute(bool $dropPendingUpdates = false): array
    {
        $secret = (string) config('social.telegram.webhook_secret');
        if (! preg_match('/^[A-Za-z0-9_-]{16,256}$/', $secret)) {
            throw new DomainException('TELEGRAM_WEBHOOK_SECRET must contain 16-256 letters, numbers, underscores, or hyphens.');
        }
        if (! str_starts_with((string) config('app.url'), 'https://')) {
            throw new DomainException('APP_URL must be a public HTTPS URL before configuring Telegram.');
        }

        $bot = $this->api->bot();
        if ($bot['id'] === '') {
            throw new DomainException('Telegram returned no bot identifier.');
        }

        $channel = SocialChannel::query()->firstOrCreate(
            ['platform' => SocialPlatform::Telegram->value, 'external_id' => $bot['id']],
            [
                'name' => $bot['username'] !== '' ? '@'.$bot['username'] : $bot['name'],
                'active' => false,
            ],
        );
        $channel->update(['name' => $bot['username'] !== '' ? '@'.$bot['username'] : $bot['name']]);
        $this->api->setWebhook(
            route('api.social.telegram.webhook.receive', $channel),
            $secret,
            $dropPendingUpdates,
        );
        $channel->update(['active' => true]);

        return ['channel' => $channel->refresh(), 'webhook' => $this->api->webhookInfo()];
    }
}
