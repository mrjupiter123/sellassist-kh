<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Data;

use App\Domain\Delivery\Enums\ShipmentStatus;

final readonly class ProviderShipmentResult
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $externalId,
        public ?string $trackingNumber,
        public ?string $externalStatus,
        public ?ShipmentStatus $status,
        public array $payload,
        public int $responseCode,
    ) {}
}
