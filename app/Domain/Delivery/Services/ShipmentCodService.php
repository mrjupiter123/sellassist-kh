<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Services;

use App\Domain\Delivery\Enums\CodStatus;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderCalculator;

final class ShipmentCodService
{
    public function __construct(private readonly OrderCalculator $calculator) {}

    public function outstandingMinor(Order $order): int
    {
        $paid = $this->calculator->toMinorUnits((string) $order->payments()->sum('amount'));
        $refunded = $this->calculator->toMinorUnits((string) $order->refunds()->sum('amount'));
        $total = $this->calculator->toMinorUnits($order->total);

        return max(0, $total - max(0, $paid - $refunded));
    }

    public function syncOpenShipment(Order $order): void
    {
        $outstanding = $this->outstandingMinor($order);

        $order->shipments()
            ->whereIn('status', [
                ShipmentStatus::Pending->value,
                ShipmentStatus::ReadyForPickup->value,
                ShipmentStatus::PickedUp->value,
                ShipmentStatus::InTransit->value,
                ShipmentStatus::DeliveryFailed->value,
            ])->update([
                'cod_amount' => number_format($outstanding / 100, 2, '.', ''),
                'cod_status' => $outstanding > 0 ? CodStatus::PendingCollection->value : CodStatus::NotApplicable->value,
                'updated_at' => now(),
            ]);
    }

    public function hasCollectedCod(Order $order): bool
    {
        return $order->shipments()
            ->where('status', ShipmentStatus::Delivered->value)
            ->whereIn('cod_status', [CodStatus::Collected->value, CodStatus::PartiallyRemitted->value])
            ->exists();
    }
}
