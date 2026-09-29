<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Delivery\Actions\ApplyProviderShipmentStatus;
use App\Domain\Delivery\Adapters\L192Adapter;
use App\Domain\Delivery\Enums\DeliveryAdapter;
use App\Domain\Delivery\Models\DeliveryWebhookEvent;
use App\Domain\Delivery\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessDeliveryWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $eventId) {}

    public function handle(ApplyProviderShipmentStatus $applyStatus, L192Adapter $l192): void
    {
        $event = DeliveryWebhookEvent::query()->with('provider')->findOrFail($this->eventId);
        if ($event->status === 'processed') {
            return;
        }

        try {
            $payload = $event->payload;
            $externalId = (string) (data_get($payload, 'package_id') ?: data_get($payload, 'data.package_id') ?: data_get($payload, 'id'));
            $externalStatus = strtoupper((string) (data_get($payload, 'status') ?: data_get($payload, 'data.status')));
            $status = match ($event->provider->adapter) {
                DeliveryAdapter::L192 => $l192->mapStatus($externalStatus),
                default => null,
            };

            $shipment = Shipment::query()
                ->where('delivery_provider_id', $event->provider->id)
                ->where('external_id', $externalId)
                ->first();

            if ($shipment === null || $status === null) {
                $event->update(['status' => 'ignored', 'processed_at' => now()]);

                return;
            }

            $applyStatus->execute($shipment, $status, externalStatus: $externalStatus);
            $shipment->update(['last_synced_at' => now(), 'integration_error' => null]);
            $event->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
        } catch (Throwable $exception) {
            $event->update(['status' => 'failed', 'error' => $exception->getMessage(), 'processed_at' => now()]);
            throw $exception;
        }
    }
}
