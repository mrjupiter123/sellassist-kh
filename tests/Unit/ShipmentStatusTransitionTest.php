<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\InvalidShipmentStatusTransitionException;
use App\Domain\Delivery\Services\ShipmentStatusTransition;
use PHPUnit\Framework\TestCase;

class ShipmentStatusTransitionTest extends TestCase
{
    public function test_expected_delivery_progression_is_allowed(): void
    {
        $service = new ShipmentStatusTransition;

        $this->assertContains(ShipmentStatus::ReadyForPickup, $service->allowedFrom(ShipmentStatus::Pending));
        $this->assertContains(ShipmentStatus::PickedUp, $service->allowedFrom(ShipmentStatus::ReadyForPickup));
        $this->assertContains(ShipmentStatus::Delivered, $service->allowedFrom(ShipmentStatus::InTransit));
    }

    public function test_delivered_shipment_is_terminal(): void
    {
        $this->expectException(InvalidShipmentStatusTransitionException::class);

        (new ShipmentStatusTransition)->assertCanTransition(ShipmentStatus::Delivered, ShipmentStatus::Pending);
    }
}
