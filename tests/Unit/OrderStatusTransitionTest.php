<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Exceptions\InvalidOrderStatusTransitionException;
use App\Domain\Order\Services\OrderStatusTransition;
use PHPUnit\Framework\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    public function test_expected_forward_transitions_are_allowed(): void
    {
        $service = new OrderStatusTransition;

        $service->assertCanTransition(OrderStatus::New, OrderStatus::Confirmed);
        $service->assertCanTransition(OrderStatus::Confirmed, OrderStatus::Packed);
        $service->assertCanTransition(OrderStatus::Shipped, OrderStatus::Completed);

        $this->addToAssertionCount(3);
    }

    public function test_completed_order_cannot_return_to_new(): void
    {
        $this->expectException(InvalidOrderStatusTransitionException::class);

        (new OrderStatusTransition)->assertCanTransition(OrderStatus::Completed, OrderStatus::New);
    }
}
