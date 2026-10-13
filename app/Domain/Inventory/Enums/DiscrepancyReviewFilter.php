<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

enum DiscrepancyReviewFilter: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Reviewed => 'Reviewed',
        };
    }
}
