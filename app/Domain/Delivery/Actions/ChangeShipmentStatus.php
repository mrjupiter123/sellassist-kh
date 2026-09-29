<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Enums\CodStatus;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\InvalidShipmentException;
use App\Domain\Delivery\Models\Shipment;
use App\Domain\Delivery\Services\ShipmentStatusTransition;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\OrderActivity\Enums\OrderActivityType;
use App\Domain\OrderActivity\Services\RecordOrderActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ChangeShipmentStatus
{
    public function __construct(
        private readonly ShipmentStatusTransition $transitions,
        private readonly ChangeOrderStatus $changeOrderStatus,
        private readonly RecordOrderActivity $recordActivity,
    ) {}

    public function execute(
        Shipment $shipment,
        ShipmentStatus $targetStatus,
        ?User $changedBy,
        ?string $notes = null,
        ?string $trackingNumber = null,
    ): Shipment {
        return DB::transaction(function () use ($shipment, $targetStatus, $changedBy, $notes, $trackingNumber): Shipment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($shipment->order_id);
            $lockedShipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $lockedShipment->setRelation('order', $lockedOrder);

            if ($lockedShipment->status === $targetStatus) {
                return $lockedShipment;
            }

            $previousStatus = $lockedShipment->status;
            $this->transitions->assertCanTransition($previousStatus, $targetStatus);
            $order = $lockedOrder;

            if ($targetStatus === ShipmentStatus::PickedUp) {
                if ($order->status === OrderStatus::Packed) {
                    $this->changeOrderStatus->execute($order, OrderStatus::Shipped, $changedBy);
                } elseif ($order->status !== OrderStatus::Shipped) {
                    throw new InvalidShipmentException('Pack the order before recording courier pickup.');
                }
            }

            if ($targetStatus === ShipmentStatus::Delivered) {
                if ($order->status !== OrderStatus::Shipped) {
                    throw new InvalidShipmentException('Only a shipped order can be marked delivered.');
                }

                $this->changeOrderStatus->execute($order, OrderStatus::Completed, $changedBy);
            }

            if ($targetStatus === ShipmentStatus::Returned && $order->status === OrderStatus::Shipped) {
                $this->changeOrderStatus->execute($order, OrderStatus::Cancelled, $changedBy, fromShipment: true);
            }

            $attributes = ['status' => $targetStatus];
            if ($trackingNumber !== null) {
                $attributes['tracking_number'] = $trackingNumber;
            }

            $timestampColumn = match ($targetStatus) {
                ShipmentStatus::ReadyForPickup => 'ready_at',
                ShipmentStatus::PickedUp => 'picked_up_at',
                ShipmentStatus::Delivered => 'delivered_at',
                ShipmentStatus::DeliveryFailed => 'failed_at',
                ShipmentStatus::Returned => 'returned_at',
                ShipmentStatus::Cancelled => 'cancelled_at',
                default => null,
            };
            if ($timestampColumn !== null) {
                $attributes[$timestampColumn] = now();
            }

            if ($targetStatus === ShipmentStatus::Delivered && (float) $lockedShipment->cod_amount > 0) {
                $attributes['cod_status'] = CodStatus::Collected;
            }
            if (in_array($targetStatus, [ShipmentStatus::Returned, ShipmentStatus::Cancelled], true)
                && (float) $lockedShipment->cod_amount > 0) {
                $attributes['cod_status'] = CodStatus::Cancelled;
            }

            $lockedShipment->update($attributes);
            $lockedShipment->histories()->create([
                'status' => $targetStatus,
                'notes' => $notes,
                'occurred_at' => now(),
                'created_by' => $changedBy?->id,
            ]);
            $this->recordActivity->execute(
                $order,
                OrderActivityType::ShipmentStatusChanged,
                sprintf('Shipment %s changed from %s to %s.', $lockedShipment->shipment_number, $previousStatus->label(), $targetStatus->label()),
                ['shipment_uuid' => $lockedShipment->uuid, 'from' => $previousStatus->value, 'to' => $targetStatus->value],
                $changedBy,
            );

            return $lockedShipment->refresh()->load(['order', 'provider', 'histories.creator', 'remittances.creator']);
        });
    }
}
