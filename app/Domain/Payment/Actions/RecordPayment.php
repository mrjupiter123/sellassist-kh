<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Delivery\Services\ShipmentCodService;
use App\Domain\Order\Exceptions\InvalidOrderTotalException;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderCalculator;
use App\Domain\OrderActivity\Enums\OrderActivityType;
use App\Domain\OrderActivity\Services\RecordOrderActivity;
use App\Domain\Payment\Exceptions\InvalidPaymentAmountException;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentStatusService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RecordPayment
{
    public function __construct(
        private readonly OrderCalculator $calculator,
        private readonly PaymentStatusService $paymentStatus,
        private readonly RecordOrderActivity $recordActivity,
        private readonly ShipmentCodService $shipmentCod,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(Order $order, array $data, User $createdBy, bool $fromCodRemittance = false): Payment
    {
        return DB::transaction(function () use ($order, $data, $createdBy, $fromCodRemittance): Payment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $fromCodRemittance && $this->shipmentCod->hasCollectedCod($lockedOrder)) {
                throw new InvalidPaymentAmountException('Reconcile the delivered shipment through its COD remittance workflow.');
            }

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

            $this->paymentStatus->refresh($lockedOrder);
            $this->shipmentCod->syncOpenShipment($lockedOrder);

            $this->recordActivity->execute(
                $lockedOrder,
                OrderActivityType::PaymentRecorded,
                sprintf('Payment of %s %s recorded.', $lockedOrder->currency->value, number_format((float) $payment->amount, 2)),
                ['payment_uuid' => $payment->uuid, 'amount' => $payment->amount, 'currency' => $lockedOrder->currency->value],
                $createdBy,
            );

            return $payment->load(['order', 'creator']);
        });
    }
}
