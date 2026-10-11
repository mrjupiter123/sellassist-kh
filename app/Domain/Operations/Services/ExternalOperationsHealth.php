<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Enums\OperationsHeartbeatName;
use App\Domain\Operations\Enums\QueueHealthStatus;
use App\Domain\Operations\Models\OperationsHeartbeat;
use App\Domain\Operations\Models\QueueStallAlert;

final class ExternalOperationsHealth
{
    public function isHealthy(): bool
    {
        if (config('queue.default') !== 'database') {
            return false;
        }

        $freshAfter = now()->subMinutes(max(1, (int) config('operations.heartbeat_max_age_minutes', 15)));
        $heartbeats = OperationsHeartbeat::query()
            ->whereIn('name', [OperationsHeartbeatName::Scheduler->value, OperationsHeartbeatName::IntegrationsWorker->value])
            ->get()
            ->keyBy('name');

        foreach (OperationsHeartbeatName::cases() as $name) {
            if (! $heartbeats->get($name->value)?->last_seen_at?->gte($freshAfter)) {
                return false;
            }
        }

        $queueAlerts = QueueStallAlert::query()->whereIn('queue', ['integrations', 'default'])->get()->keyBy('queue');
        foreach (['integrations', 'default'] as $queue) {
            $alert = $queueAlerts->get($queue);
            if ($alert?->status !== QueueHealthStatus::Healthy
                || ! $alert->last_checked_at?->gte($freshAfter)) {
                return false;
            }
        }

        return true;
    }
}
