<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Adapters;

use App\Domain\Delivery\Contracts\DeliveryProviderAdapter;
use App\Domain\Delivery\Data\ProviderShipmentResult;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\DeliveryIntegrationException;
use App\Domain\Delivery\Models\Shipment;
use App\Domain\Payment\Enums\Currency;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

final class L192Adapter implements DeliveryProviderAdapter
{
    public function create(Shipment $shipment): ProviderShipmentResult
    {
        if ($shipment->currency !== Currency::Usd) {
            throw new DeliveryIntegrationException('L192 package values and COD are supported only in USD.');
        }

        $payload = array_filter([
            'customer_name' => $shipment->recipient_name,
            'phone_number' => $shipment->recipient_phone,
            'address_name' => $shipment->delivery_address,
            'value' => (float) $shipment->order->total,
            'cash' => (float) $shipment->cod_amount,
            'instruction' => $shipment->notes,
            'sender' => $this->sender(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $response = $this->client()->post('/v1/packages', $payload)->throw();
        $body = $response->json();
        $externalId = (string) data_get($body, 'data.package_id', '');

        if ($externalId === '') {
            throw new DeliveryIntegrationException('L192 did not return a package_id.');
        }

        return new ProviderShipmentResult($externalId, $externalId, 'DRAFT', ShipmentStatus::Pending, $body, $response->status());
    }

    public function track(Shipment $shipment): ProviderShipmentResult
    {
        if (blank($shipment->external_id)) {
            throw new DeliveryIntegrationException('The shipment has not been submitted to L192.');
        }

        $response = $this->client()->get('/v1/packages/'.$shipment->external_id)->throw();
        $body = $response->json();
        $externalStatus = strtoupper((string) data_get($body, 'data.status'));

        return new ProviderShipmentResult(
            $shipment->external_id,
            $shipment->tracking_number,
            $externalStatus,
            $this->mapStatus($externalStatus),
            $body,
            $response->status(),
        );
    }

    public function mapStatus(string $status): ?ShipmentStatus
    {
        return match (strtoupper($status)) {
            'DRAFT' => ShipmentStatus::Pending,
            'SCHEDULE_REQUEST_PICKUP', 'REQUEST_PICKUP' => ShipmentStatus::ReadyForPickup,
            'CLAIM_PICKUP', 'PICKUP' => ShipmentStatus::PickedUp,
            'ARRIVED', 'TRANSIT', 'DELIVERING' => ShipmentStatus::InTransit,
            'DELIVERED' => ShipmentStatus::Delivered,
            'DELETED' => ShipmentStatus::Cancelled,
            default => null,
        };
    }

    private function client(): PendingRequest
    {
        $token = (string) config('delivery.adapters.l192.token');
        if ($token === '') {
            throw new DeliveryIntegrationException('L192_API_TOKEN is not configured.');
        }

        $header = (string) config('delivery.adapters.l192.auth_header', 'Authorization');
        $prefix = trim((string) config('delivery.adapters.l192.auth_prefix', 'Bearer'));

        return Http::baseUrl((string) config('delivery.adapters.l192.base_url'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([$header => trim($prefix.' '.$token)])
            ->timeout(15);
    }

    /** @return array<string, mixed>|null */
    private function sender(): ?array
    {
        $sender = array_filter(Arr::only((array) config('delivery.adapters.l192.sender'), [
            'name', 'phone_number', 'address_name', 'lat', 'lng',
        ]), fn (mixed $value): bool => filled($value));

        return $sender === [] ? null : $sender;
    }
}
