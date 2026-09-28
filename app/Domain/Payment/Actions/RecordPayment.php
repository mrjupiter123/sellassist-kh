<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Exceptions\InvalidOrderTotalException;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderCalculator;
use App\Domain\Payment\Exceptions\InvalidPaymentAmountException;
use App\Domain\Payment\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RecordPayment
{
    public function __construct(private readonly OrderCalculator $calculator) {}

    /** @param array<string, mixed> $data */
    public function execute(Order $order, array $data, User $createdBy): Payment
    {
        return DB::transaction(function () use ($order, $data, $createdBy): Payment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ((string) $lockedOrder->currency->value !== (string) $data['currency']) {
                throw new InvalidPaymentAmountException('Payment currency must match the order currency.');
            }

            try {
                $amount = $this->calculator->toMinorUnits($data['amount']);
                $orderTotal = $this->calculator->toMinorUnits($lockedOrder->total);
                $alreadyPaid = $this->calculator->toMinorUnits((string) $lockedOrder->payments()->sum('amount'));
            } catch (InvalidOrderTotalException $exception) {
                throw new InvalidPaymentAmountException($exception->getMessage(), previous: $exception);
            }

            if ($amount <= 0) {
                throw new InvalidPaymentAmountException('Payment amount must be greater than zero.');
            }

            if ($alreadyPaid + $amount > $orderTotal) {
                throw new InvalidPaymentAmountException('Payment exceeds the remaining order balance.');
            }

            $payment = $lockedOrder->payments()->create([
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'created_by' => $createdBy->id,
            ]);

            $paidTotal = $alreadyPaid + $amount;
            $lockedOrder->update([
                'payment_status' => $paidTotal === $orderTotal
                    ? PaymentStatus::Paid
                    : PaymentStatus::PartiallyPaid,
            ]);

            return $payment->load(['order', 'creator']);
        });
    }
}
