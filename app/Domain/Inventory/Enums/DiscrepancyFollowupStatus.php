<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

enum DiscrepancyFollowupStatus: string
{
    case Open = 'open';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Completed => 'Completed',
        };
    }
}
