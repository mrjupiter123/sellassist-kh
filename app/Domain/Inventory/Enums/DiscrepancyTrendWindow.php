<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

enum DiscrepancyTrendWindow: string
{
    case SevenDays = '7';
    case ThirtyDays = '30';
    case NinetyDays = '90';

    public function days(): int
    {
        return (int) $this->value;
    }

    public function label(): string
    {
        return 'Last '.$this->value.' days';
    }
}
