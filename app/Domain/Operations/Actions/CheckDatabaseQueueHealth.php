<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\QueueHealthStatus;
use App\Domain\Operations\Models\QueueStallAlert;
use App\Models\User;
use App\Notifications\DatabaseQueueStalled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class CheckDatabaseQueueHealth
{
    /** @return array<string, string> */
    public function execute(): array
    {
        if (config('queue.default') !== 'database') {
            return ['monitor' => 'unsupported'];
        }

        $thresholdMinutes = max(1, (int) config('operations.queue_stale_minutes', 15));
        $cutoff = now()->subMinutes($thresholdMinutes)->timestamp;
        $queueDatabase = DB::connection(config('queue.connections.database.connection'));
        $queueTable = config('queue.connections.database.table', 'jobs');
        $results = [];

        foreach (['integrations', 'default'] as $queue) {
            QueueStallAlert::query()->firstOrCreate(
                ['queue' => $queue],
                ['status' => QueueHealthStatus::Healthy, 'last_checked_at' => now()],
            );

            $results[$queue] = DB::transaction(function () use ($queue, $queueDatabase, $queueTable, $cutoff, $thresholdMinutes): string {
                $alert = QueueStallAlert::query()->where('queue', $queue)->lockForUpdate()->firstOrFail();
                $staleJobs = $queueDatabase->table($queueTable)
                    ->where('queue', $queue)
                    ->where('available_at', '<=', $cutoff)
                    ->where(function ($query) use ($cutoff): void {
                        $query->whereNull('reserved_at')->orWhere('reserved_at', '<=', $cutoff);
                    })
                    ->count();

                if ($staleJobs === 0) {
                    $wasStalled = $alert->status === QueueHealthStatus::Stalled;
                    $alert->update([
                        'status' => QueueHealthStatus::Healthy,
                        'stale_jobs' => 0,
                        'stalled_since' => null,
                        'last_checked_at' => now(),
                        'last_notified_at' => null,
                        'recovered_at' => $wasStalled ? now() : $alert->recovered_at,
                    ]);

                    return QueueHealthStatus::Healthy->value;
                }

                $newStall = $alert->status !== QueueHealthStatus::Stalled;
                $administrators = $newStall || $alert->last_notified_at === null
                    ? User::permission('operations.view')->where('active', true)->get()
                    : collect();

                if ($administrators->isNotEmpty()) {
                    Notification::send($administrators, new DatabaseQueueStalled($queue, $staleJobs, $thresholdMinutes));
                }

                $alert->update([
                    'status' => QueueHealthStatus::Stalled,
                    'stale_jobs' => $staleJobs,
                    'stalled_since' => $newStall ? now() : $alert->stalled_since,
                    'last_checked_at' => now(),
                    'last_notified_at' => $administrators->isNotEmpty() ? now() : $alert->last_notified_at,
                    'recovered_at' => null,
                ]);

                return QueueHealthStatus::Stalled->value;
            });
        }

        return $results;
    }
}
