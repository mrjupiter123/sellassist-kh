<?php

declare(strict_types=1);

namespace App\Domain\Order\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case New = 'new';
    case Confirmed = 'confirmed';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function holdsStock(): bool
    {
        return in_array($this, [self::Confirmed, self::Packed, self::Shipped], true);
    }
}
