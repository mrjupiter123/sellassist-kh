<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Order\Exceptions\InvalidOrderTotalException;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderCalculator;
use App\Domain\OrderReturn\Models\OrderReturn;
use App\Domain\Payment\Exceptions\InvalidRefundAmountException;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Refund;
use App\Domain\Payment\Services\PaymentStatusService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RecordRefund
{
    public function __construct(
        private readonly OrderCalculator $calculator,
        private readonly PaymentStatusService $paymentStatus,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(Order $order, Payment $payment, array $data, User $createdBy, ?OrderReturn $orderReturn = null): Refund
    {
        return DB::transaction(function () use ($order, $payment, $data, $createdBy, $orderReturn): Refund {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->order_id !== $lockedOrder->id) {
                throw new InvalidRefundAmountException('The selected payment does not belong to this order.');
            }

            if ($orderReturn !== null && $orderReturn->order_id !== $lockedOrder->id) {
                throw new InvalidRefundAmountException('The selected return does not belong to this order.');
            }

            if ($lockedPayment->currency !== $lockedOrder->currency) {
                throw new InvalidRefundAmountException('Refund currency must match the order payment currency.');
            }

            try {
                $amount = $this->calculator->toMinorUnits($data['amount']);
                $paymentAmount = $this->calculator->toMinorUnits($lockedPayment->amount);
                $alreadyRefunded = $this->calculator->toMinorUnits((string) $lockedPayment->refunds()->sum('amount'));
            } catch (InvalidOrderTotalException $exception) {
                throw new InvalidRefundAmountException($exception->getMessage(), previous: $exception);
            }

            if ($amount <= 0) {
                throw new InvalidRefundAmountException('Refund amount must be greater than zero.');
            }

            if ($alreadyRefunded + $amount > $paymentAmount) {
                throw new InvalidRefundAmountException('Refund exceeds the refundable balance of the selected payment.');
            }

            $refund = Refund::query()->create([
                'order_id' => $lockedOrder->id,
                'payment_id' => $lockedPayment->id,
                'order_return_id' => $orderReturn?->id,
                'amount' => $data['amount'],
                'currency' => $lockedOrder->currency,
                'refund_method' => $data['refund_method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'refunded_at' => $data['refunded_at'] ?? now(),
                'created_by' => $createdBy->id,
            ]);

            $this->paymentStatus->refresh($lockedOrder);

            return $refund->load(['payment', 'orderReturn', 'creator']);
        });
    }
}
