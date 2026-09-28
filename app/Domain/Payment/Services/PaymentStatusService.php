<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderCalculator;

final class PaymentStatusService
{
    public function __construct(private readonly OrderCalculator $calculator) {}

    public function refresh(Order $order): PaymentStatus
    {
        $paid = $this->calculator->toMinorUnits((string) $order->payments()->sum('amount'));
        $refunded = $this->calculator->toMinorUnits((string) $order->refunds()->sum('amount'));
        $total = $this->calculator->toMinorUnits($order->total);

        $status = match (true) {
            $paid === 0 => PaymentStatus::Unpaid,
            $refunded >= $paid => PaymentStatus::Refunded,
            $refunded > 0 => PaymentStatus::PartiallyRefunded,
            $paid >= $total => PaymentStatus::Paid,
            default => PaymentStatus::PartiallyPaid,
        };

        $order->update(['payment_status' => $status]);

        return $status;
    }
}
