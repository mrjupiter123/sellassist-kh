<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Social\Data\OutboundSocialMessageResult;
use App\Domain\Social\Exceptions\SocialMessageDeliveryException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class MetaMessengerApi
{
    public function sendText(string $recipientId, string $text): OutboundSocialMessageResult
    {
        $token = (string) config('social.meta.page_access_token');
        $version = preg_replace('/[^a-zA-Z0-9.]/', '', (string) config('social.meta.graph_version', 'v26.0')) ?: 'v26.0';
        if ($token === '') {
            throw new SocialMessageDeliveryException('META_PAGE_ACCESS_TOKEN is not configured.');
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->post("https://graph.facebook.com/{$version}/me/messages", [
                    'recipient' => ['id' => $recipientId],
                    'messaging_type' => 'RESPONSE',
                    'message' => ['text' => $text],
                ]);
        } catch (ConnectionException) {
            throw new SocialMessageDeliveryException('Meta could not be reached. Check outbound HTTPS access and try again.');
        }

        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload) || blank($payload['message_id'] ?? null)) {
            $code = is_array($payload) ? data_get($payload, 'error.code') : null;
            throw new SocialMessageDeliveryException(
                $code ? "Meta rejected the message with error code {$code}." : 'Meta rejected the message.',
            );
        }

        return new OutboundSocialMessageResult(
            externalId: (string) $payload['message_id'],
            sentAt: CarbonImmutable::now(),
            payload: $payload,
        );
    }
}
