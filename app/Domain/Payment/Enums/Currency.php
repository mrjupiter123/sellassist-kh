<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum Currency: string
{
    case Usd = 'USD';
    case Khr = 'KHR';
}
