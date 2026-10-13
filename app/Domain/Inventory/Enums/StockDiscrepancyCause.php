<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

enum StockDiscrepancyCause: string
{
    case CountError = 'count_error';
    case Receiving = 'receiving';
    case Fulfillment = 'fulfillment';
    case DamageOrLoss = 'damage_or_loss';
    case Unexplained = 'unexplained';

    public function label(): string
    {
        return match ($this) {
            self::CountError => 'Counting error',
            self::Receiving => 'Receiving discrepancy',
            self::Fulfillment => 'Order fulfillment discrepancy',
            self::DamageOrLoss => 'Damage or loss',
            self::Unexplained => 'Unexplained',
        };
    }
}
