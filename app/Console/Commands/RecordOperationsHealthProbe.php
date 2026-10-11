<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Operations\Actions\RecordOperationsHeartbeat;
use App\Domain\Operations\Enums\OperationsHeartbeatName;
use App\Jobs\RecordIntegrationsWorkerHeartbeat;
use Illuminate\Console\Command;

class RecordOperationsHealthProbe extends Command
{
    protected $signature = 'operations:heartbeat';

    protected $description = 'Record the scheduler heartbeat and queue an integrations-worker probe';

    public function handle(RecordOperationsHeartbeat $action): int
    {
        if (config('queue.default') !== 'database'
            || ! is_string(config('operations.health_token'))
            || strlen(config('operations.health_token')) < 32) {
            $this->warn('External operations health monitoring is not enabled for the database queue.');

            return self::SUCCESS;
        }

        $action->execute(OperationsHeartbeatName::Scheduler);
        RecordIntegrationsWorkerHeartbeat::dispatch()->onQueue('integrations');
        $this->info('Scheduler heartbeat recorded; integrations-worker probe queued.');

        return self::SUCCESS;
    }
}
