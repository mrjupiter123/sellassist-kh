<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class TelegramBotApi
{
    /** @return array{id: string, username: string, name: string} */
    public function bot(): array
    {
        $result = $this->request('getMe');

        return [
            'id' => (string) ($result['id'] ?? ''),
            'username' => (string) ($result['username'] ?? ''),
            'name' => trim((string) ($result['first_name'] ?? 'Telegram Bot')),
        ];
    }

    /** @return array<string, mixed> */
    public function setWebhook(string $url, string $secret, bool $dropPendingUpdates = false): array
    {
        return $this->request('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
            'drop_pending_updates' => $dropPendingUpdates,
        ]);
    }

    /** @return array<string, mixed> */
    public function webhookInfo(): array
    {
        return $this->request('getWebhookInfo');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function request(string $method, array $data = []): array
    {
        $token = (string) config('social.telegram.bot_token');
        if ($token === '') {
            throw new DomainException('TELEGRAM_BOT_TOKEN is not configured.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(20)
                ->post('https://api.telegram.org/bot'.$token.'/'.$method, $data);
        } catch (ConnectionException) {
            throw new DomainException('Telegram could not be reached. Check outbound HTTPS access and try again.');
        }

        return $this->result($response);
    }

    /** @return array<string, mixed> */
    private function result(Response $response): array
    {
        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload) || ($payload['ok'] ?? false) !== true) {
            $description = is_array($payload) ? (string) ($payload['description'] ?? '') : '';
            throw new DomainException($description !== ''
                ? 'Telegram rejected the request: '.$description
                : 'Telegram rejected the request.');
        }

        return is_array($payload['result'] ?? null) ? $payload['result'] : [];
    }
}
