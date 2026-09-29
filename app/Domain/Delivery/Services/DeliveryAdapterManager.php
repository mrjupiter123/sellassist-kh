<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Services;

use App\Domain\Delivery\Adapters\L192Adapter;
use App\Domain\Delivery\Contracts\DeliveryProviderAdapter;
use App\Domain\Delivery\Enums\DeliveryAdapter;
use App\Domain\Delivery\Exceptions\DeliveryIntegrationException;
use App\Domain\Delivery\Models\DeliveryProvider;

final class DeliveryAdapterManager
{
    public function resolve(DeliveryProvider $provider): DeliveryProviderAdapter
    {
        return match ($provider->adapter) {
            DeliveryAdapter::L192 => app(L192Adapter::class),
            default => throw new DeliveryIntegrationException('This delivery provider does not have an API adapter.'),
        };
    }
}
