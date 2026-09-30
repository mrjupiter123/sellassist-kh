<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum Currency: string
{
    case Usd = 'USD';
    case Khr = 'KHR';

    public function label(): string
    {
        return match ($this) {
            self::Usd => 'USD ($)',
            self::Khr => 'KHR (៛)',
        };
    }

    public function format(float|int|string $amount): string
    {
        return match ($this) {
            self::Usd => 'USD $'.number_format((float) $amount, 2),
            self::Khr => 'KHR '.number_format((float) $amount, 0).' ៛',
        };
    }
}
