<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Enums\CodStatus;
use App\Domain\Delivery\Enums\DeliveryAdapter;
use App\Domain\Delivery\Enums\IntegrationStatus;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\InvalidShipmentException;
use App\Domain\Delivery\Models\DeliveryProvider;
use App\Domain\Delivery\Models\Shipment;
use App\Domain\Delivery\Services\ShipmentCodService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\OrderActivity\Enums\OrderActivityType;
use App\Domain\OrderActivity\Services\RecordOrderActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateShipment
{
    public function __construct(
        private readonly ShipmentCodService $codService,
        private readonly RecordOrderActivity $recordActivity,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(Order $order, array $data, User $createdBy): Shipment
    {
        return DB::transaction(function () use ($order, $data, $createdBy): Shipment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($lockedOrder->status, [OrderStatus::Confirmed, OrderStatus::Packed], true)) {
                throw new InvalidShipmentException('Only confirmed or packed orders can be prepared for delivery.');
            }

            $hasActiveShipment = $lockedOrder->shipments()
                ->whereNotIn('status', [
                    ShipmentStatus::Delivered->value,
                    ShipmentStatus::Returned->value,
                    ShipmentStatus::Cancelled->value,
                ])->exists();

            if ($hasActiveShipment) {
                throw new InvalidShipmentException('This order already has an active shipment.');
            }

            $provider = DeliveryProvider::query()->where('active', true)->findOrFail($data['delivery_provider_id']);
            $codMinor = $this->codService->outstandingMinor($lockedOrder);
            $codAmount = number_format($codMinor / 100, 2, '.', '');

            $shipment = Shipment::query()->create([
                'shipment_number' => 'PENDING-'.Str::uuid(),
                'order_id' => $lockedOrder->id,
                'delivery_provider_id' => $provider->id,
                'tracking_number' => $data['tracking_number'] ?? null,
                'status' => ShipmentStatus::Pending,
                'cod_amount' => $codAmount,
                'cod_status' => $codMinor > 0 ? CodStatus::PendingCollection : CodStatus::NotApplicable,
                'currency' => $lockedOrder->currency,
                'recipient_name' => $lockedOrder->customer_name,
                'recipient_phone' => $lockedOrder->customer_phone,
                'delivery_address' => $lockedOrder->shipping_address,
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy->id,
                'integration_status' => $provider->integration_enabled && $provider->adapter !== DeliveryAdapter::Manual
                    ? IntegrationStatus::NotSubmitted
                    : IntegrationStatus::Manual,
            ]);
            $shipment->update([
                'shipment_number' => 'SHP-'.$shipment->created_at->format('Ymd').'-'.str_pad((string) $shipment->id, 4, '0', STR_PAD_LEFT),
            ]);
            $shipment->histories()->create([
                'status' => ShipmentStatus::Pending,
                'notes' => 'Shipment created.',
                'occurred_at' => now(),
                'created_by' => $createdBy->id,
            ]);

            $this->recordActivity->execute(
                $lockedOrder,
                OrderActivityType::ShipmentCreated,
                "Shipment {$shipment->shipment_number} created with {$provider->name}.",
                ['shipment_uuid' => $shipment->uuid, 'shipment_number' => $shipment->shipment_number],
                $createdBy,
            );

            return $shipment->load(['order', 'provider', 'histories.creator']);
        });
    }
}
