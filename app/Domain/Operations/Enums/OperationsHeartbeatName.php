<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum OperationsHeartbeatName: string
{
    case Scheduler = 'scheduler';
    case IntegrationsWorker = 'integrations_worker';
}
