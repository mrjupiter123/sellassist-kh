<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Services;

use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\InvalidShipmentStatusTransitionException;

final class ShipmentStatusTransition
{
    /** @return list<ShipmentStatus> */
    public function allowedFrom(ShipmentStatus $status): array
    {
        return match ($status) {
            ShipmentStatus::Pending => [ShipmentStatus::ReadyForPickup, ShipmentStatus::Cancelled],
            ShipmentStatus::ReadyForPickup => [ShipmentStatus::PickedUp, ShipmentStatus::Cancelled],
            ShipmentStatus::PickedUp => [ShipmentStatus::InTransit, ShipmentStatus::DeliveryFailed, ShipmentStatus::Returned],
            ShipmentStatus::InTransit => [ShipmentStatus::Delivered, ShipmentStatus::DeliveryFailed, ShipmentStatus::Returned],
            ShipmentStatus::DeliveryFailed => [ShipmentStatus::ReadyForPickup, ShipmentStatus::Returned],
            ShipmentStatus::Delivered, ShipmentStatus::Returned, ShipmentStatus::Cancelled => [],
        };
    }

    public function assertCanTransition(ShipmentStatus $from, ShipmentStatus $to): void
    {
        if (! in_array($to, $this->allowedFrom($from), true)) {
            throw InvalidShipmentStatusTransitionException::fromStatuses($from, $to);
        }
    }
}
