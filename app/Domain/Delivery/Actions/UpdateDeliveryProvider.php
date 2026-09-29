<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Enums\DeliveryAdapter;
use App\Domain\Delivery\Models\DeliveryProvider;
use Illuminate\Validation\ValidationException;

final class UpdateDeliveryProvider
{
    /** @param array<string, mixed> $data */
    public function execute(DeliveryProvider $provider, array $data): DeliveryProvider
    {
        if ($provider->adapter->value !== $data['adapter'] && $provider->shipments()->whereNotNull('external_id')->exists()) {
            throw ValidationException::withMessages([
                'adapter' => 'The adapter cannot change after shipments have been submitted to this provider.',
            ]);
        }

        if ($data['adapter'] === DeliveryAdapter::Manual->value) {
            $data['integration_enabled'] = false;
        }

        $provider->update($data);

        return $provider->refresh();
    }
}
