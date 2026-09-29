<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Enums\DeliveryAdapter;
use App\Domain\Delivery\Models\DeliveryProvider;

final class CreateDeliveryProvider
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): DeliveryProvider
    {
        if ($data['adapter'] === DeliveryAdapter::Manual->value) {
            $data['integration_enabled'] = false;
        }

        return DeliveryProvider::query()->create($data);
    }
}
