<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum QueueHealthStatus: string
{
    case Healthy = 'healthy';
    case Stalled = 'stalled';
}
