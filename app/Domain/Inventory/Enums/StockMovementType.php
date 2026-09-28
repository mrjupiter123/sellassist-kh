<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

enum StockMovementType: string
{
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';
    case Order = 'order';
    case Adjustment = 'adjustment';
    case Return = 'return';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
