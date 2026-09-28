<?php

declare(strict_types=1);

namespace App\Domain\OrderActivity\Services;

use App\Domain\Order\Models\Order;
use App\Domain\OrderActivity\Enums\OrderActivityType;
use App\Domain\OrderActivity\Models\OrderActivity;
use App\Models\User;

final class RecordOrderActivity
{
    /** @param array<string, mixed> $metadata */
    public function execute(
        Order $order,
        OrderActivityType $type,
        string $description,
        array $metadata = [],
        ?User $createdBy = null,
    ): OrderActivity {
        return $order->activities()->create([
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_by' => $createdBy?->getKey(),
        ]);
    }
}
