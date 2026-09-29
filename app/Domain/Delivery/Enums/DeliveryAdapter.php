<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Enums;

enum DeliveryAdapter: string
{
    case Manual = 'manual';
    case L192 = 'l192';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual / no API',
            self::L192 => 'L192 Delivery',
        };
    }
}
