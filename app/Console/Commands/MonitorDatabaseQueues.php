<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Operations\Actions\CheckDatabaseQueueHealth;
use Illuminate\Console\Command;

class MonitorDatabaseQueues extends Command
{
    protected $signature = 'operations:monitor-queues';

    protected $description = 'Detect stale ready jobs in the database queues and notify administrators';

    public function handle(CheckDatabaseQueueHealth $action): int
    {
        foreach ($action->execute() as $queue => $status) {
            $this->line("{$queue}: {$status}");
        }

        return self::SUCCESS;
    }
}
