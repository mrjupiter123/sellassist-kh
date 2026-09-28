<?php

declare(strict_types=1);

namespace App\Domain\Order\Services;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Exceptions\InvalidOrderStatusTransitionException;

final class OrderStatusTransition
{
    /** @return list<OrderStatus> */
    public function allowedFrom(OrderStatus $status): array
    {
        return match ($status) {
            OrderStatus::Draft => [OrderStatus::New, OrderStatus::Cancelled],
            OrderStatus::New => [OrderStatus::Confirmed, OrderStatus::Cancelled],
            OrderStatus::Confirmed => [OrderStatus::Packed, OrderStatus::Cancelled],
            OrderStatus::Packed => [OrderStatus::Shipped, OrderStatus::Cancelled],
            OrderStatus::Shipped => [OrderStatus::Completed, OrderStatus::Cancelled],
            OrderStatus::Completed, OrderStatus::Cancelled => [],
        };
    }

    public function assertCanTransition(OrderStatus $from, OrderStatus $to): void
    {
        if (! in_array($to, $this->allowedFrom($from), true)) {
            throw InvalidOrderStatusTransitionException::fromStatuses($from, $to);
        }
    }
}
