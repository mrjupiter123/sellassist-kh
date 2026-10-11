<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Operations\Actions\RecordOperationsHeartbeat;
use App\Domain\Operations\Enums\OperationsHeartbeatName;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RecordIntegrationsWorkerHeartbeat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(RecordOperationsHeartbeat $action): void
    {
        $action->execute(OperationsHeartbeatName::IntegrationsWorker);
    }
}
