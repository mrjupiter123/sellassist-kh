<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Models\Shipment;
use App\Models\User;

final class ApplyProviderShipmentStatus
{
    public function __construct(private readonly ChangeShipmentStatus $changeStatus) {}

    public function execute(Shipment $shipment, ShipmentStatus $target, ?User $actor = null, ?string $externalStatus = null): Shipment
    {
        $shipment->refresh();
        if ($shipment->status === $target || $shipment->status->isTerminal()) {
            return $shipment;
        }

        $note = 'Provider status'.($externalStatus ? ": {$externalStatus}" : ' update');
        $progression = [
            ShipmentStatus::Pending,
            ShipmentStatus::ReadyForPickup,
            ShipmentStatus::PickedUp,
            ShipmentStatus::InTransit,
            ShipmentStatus::Delivered,
        ];
        $currentIndex = array_search($shipment->status, $progression, true);
        $targetIndex = array_search($target, $progression, true);

        if ($shipment->status === ShipmentStatus::DeliveryFailed && in_array($target, [ShipmentStatus::ReadyForPickup, ShipmentStatus::PickedUp, ShipmentStatus::InTransit], true)) {
            $shipment = $this->changeStatus->execute($shipment, ShipmentStatus::ReadyForPickup, $actor, $note);
            $currentIndex = 1;
        }

        if ($currentIndex !== false && $targetIndex !== false) {
            if ($targetIndex <= $currentIndex) {
                return $shipment;
            }

            foreach (array_slice($progression, $currentIndex + 1, $targetIndex - $currentIndex) as $status) {
                $shipment = $this->changeStatus->execute($shipment, $status, $actor, $note);
            }

            return $shipment;
        }

        return $this->changeStatus->execute($shipment, $target, $actor, $note);
    }
}
