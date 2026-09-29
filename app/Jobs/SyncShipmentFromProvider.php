<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Delivery\Actions\ApplyProviderShipmentStatus;
use App\Domain\Delivery\Enums\IntegrationStatus;
use App\Domain\Delivery\Exceptions\DeliveryIntegrationException;
use App\Domain\Delivery\Models\DeliveryIntegrationLog;
use App\Domain\Delivery\Models\Shipment;
use App\Domain\Delivery\Services\DeliveryAdapterManager;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncShipmentFromProvider implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $shipmentId, public ?int $userId = null) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping("delivery-sync-{$this->shipmentId}"))->expireAfter(60)];
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(DeliveryAdapterManager $adapters, ApplyProviderShipmentStatus $applyStatus): void
    {
        $shipment = Shipment::query()->with(['provider', 'order'])->findOrFail($this->shipmentId);

        try {
            $result = $adapters->resolve($shipment->provider)->track($shipment);
            if ($result->status !== null) {
                $shipment = $applyStatus->execute(
                    $shipment,
                    $result->status,
                    $this->userId ? User::query()->find($this->userId) : null,
                    $result->externalStatus,
                );
            }
            $shipment->update([
                'integration_status' => IntegrationStatus::Synced,
                'integration_error' => null,
                'last_synced_at' => now(),
            ]);
            DeliveryIntegrationLog::query()->create([
                'shipment_id' => $shipment->id,
                'operation' => 'track',
                'status' => 'succeeded',
                'response_code' => $result->responseCode,
                'response_payload' => $result->payload,
            ]);
        } catch (Throwable $exception) {
            $message = $this->safeError($exception);
            $shipment->update(['integration_status' => IntegrationStatus::Failed, 'integration_error' => $message]);
            DeliveryIntegrationLog::query()->create([
                'shipment_id' => $shipment->id,
                'operation' => 'track',
                'status' => 'failed',
                'response_code' => $exception instanceof RequestException ? $exception->response->status() : null,
                'response_payload' => $exception instanceof RequestException ? $exception->response->json() : null,
                'error' => $message,
            ]);
            throw $exception;
        }
    }

    private function safeError(Throwable $exception): string
    {
        if ($exception instanceof DeliveryIntegrationException) {
            return $exception->getMessage();
        }

        if ($exception instanceof RequestException) {
            return 'Provider HTTP request failed with status '.$exception->response->status().'.';
        }

        return 'Provider request failed. Review the application log for internal details.';
    }
}
