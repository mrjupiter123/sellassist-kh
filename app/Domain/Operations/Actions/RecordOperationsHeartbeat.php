<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\OperationsHeartbeatName;
use App\Domain\Operations\Models\OperationsHeartbeat;

final class RecordOperationsHeartbeat
{
    public function execute(OperationsHeartbeatName $name): void
    {
        OperationsHeartbeat::query()->upsert(
            [[
                'name' => $name->value,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['name'],
            ['last_seen_at', 'updated_at'],
        );
    }
}
