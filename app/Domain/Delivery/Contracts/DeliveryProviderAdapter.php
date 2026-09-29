<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Contracts;

use App\Domain\Delivery\Data\ProviderShipmentResult;
use App\Domain\Delivery\Models\Shipment;

interface DeliveryProviderAdapter
{
    public function create(Shipment $shipment): ProviderShipmentResult;

    public function track(Shipment $shipment): ProviderShipmentResult;
}
