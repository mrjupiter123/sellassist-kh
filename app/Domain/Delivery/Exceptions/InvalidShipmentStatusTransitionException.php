<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use App\Domain\Delivery\Enums\ShipmentStatus;
use DomainException;

class InvalidShipmentStatusTransitionException extends DomainException
{
    public static function fromStatuses(ShipmentStatus $from, ShipmentStatus $to): self
    {
        return new self("Shipment cannot transition from {$from->label()} to {$to->label()}.");
    }
}
