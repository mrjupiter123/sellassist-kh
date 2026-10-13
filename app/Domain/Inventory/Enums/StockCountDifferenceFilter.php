<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

enum StockCountDifferenceFilter: string
{
    case Changed = 'changed';
    case Unchanged = 'unchanged';

    public function label(): string
    {
        return match ($this) {
            self::Changed => 'Discrepancies',
            self::Unchanged => 'No difference',
        };
    }
}
