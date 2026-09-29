<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case ReadyForPickup = 'ready_for_pickup';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case DeliveryFailed = 'delivery_failed';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Returned, self::Cancelled], true);
    }
}
