<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

use App\Domain\Order\Enums\OrderStatus;
use DomainException;

final class InvalidOrderStatusTransitionException extends DomainException
{
    public static function fromStatuses(OrderStatus $from, OrderStatus $to): self
    {
        return new self("An order cannot move from {$from->value} to {$to->value}.");
    }
}
