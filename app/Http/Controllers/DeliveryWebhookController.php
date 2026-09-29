<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Delivery\Enums\DeliveryAdapter;
use App\Domain\Delivery\Models\DeliveryProvider;
use App\Domain\Delivery\Models\DeliveryWebhookEvent;
use App\Jobs\ProcessDeliveryWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryWebhookController extends Controller
{
    public function __invoke(Request $request, DeliveryProvider $provider): JsonResponse
    {
        if (! $provider->active || ! $provider->integration_enabled || $provider->adapter === DeliveryAdapter::Manual) {
            return response()->json(['message' => 'Provider integration is unavailable.'], 404);
        }

        $raw = $request->getContent();
        $secret = (string) config("delivery.webhooks.secrets.{$provider->adapter->value}");
        $provided = preg_replace('/^sha256=/i', '', (string) $request->header('X-SellAssist-Signature'));
        if ($secret === '' || ! is_string($provided) || ! hash_equals(hash_hmac('sha256', $raw, $secret), $provided)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $payload = $request->json()->all();
        if ($payload === []) {
            return response()->json(['message' => 'A JSON payload is required.'], 422);
        }

        $eventId = hash('sha256', (string) ($request->header('X-Delivery-Event-Id') ?: $raw));
        $event = DeliveryWebhookEvent::query()->firstOrCreate(
            ['delivery_provider_id' => $provider->id, 'event_id' => $eventId],
            ['status' => 'received', 'payload' => $payload, 'received_at' => now()],
        );

        if ($event->wasRecentlyCreated) {
            ProcessDeliveryWebhook::dispatch($event->id)->onQueue('integrations');
        }

        return response()->json(['accepted' => true, 'duplicate' => ! $event->wasRecentlyCreated], 202);
    }
}
