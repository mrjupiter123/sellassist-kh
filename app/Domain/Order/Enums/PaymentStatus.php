<?php

declare(strict_types=1);

namespace App\Domain\Order\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
