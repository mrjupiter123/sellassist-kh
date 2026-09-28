<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum PaymentMethod: string
{
    case CashOnDelivery = 'cod';
    case Aba = 'aba';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Cash on delivery',
            self::Aba => 'ABA',
            default => str($this->value)->headline()->toString(),
        };
    }
}
