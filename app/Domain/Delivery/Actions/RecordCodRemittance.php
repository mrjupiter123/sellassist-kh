<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Actions;

use App\Domain\Delivery\Enums\CodStatus;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\InvalidCodRemittanceException;
use App\Domain\Delivery\Models\CodRemittance;
use App\Domain\Delivery\Models\Shipment;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderCalculator;
use App\Domain\OrderActivity\Enums\OrderActivityType;
use App\Domain\OrderActivity\Services\RecordOrderActivity;
use App\Domain\Payment\Actions\RecordPayment;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RecordCodRemittance
{
    public function __construct(
        private readonly OrderCalculator $calculator,
        private readonly RecordPayment $recordPayment,
        private readonly RecordOrderActivity $recordActivity,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(Shipment $shipment, array $data, User $createdBy): CodRemittance
    {
        return DB::transaction(function () use ($shipment, $data, $createdBy): CodRemittance {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($shipment->order_id);
            $lockedShipment = Shipment::query()->with('provider')->lockForUpdate()->findOrFail($shipment->id);
            $lockedShipment->setRelation('order', $lockedOrder);

            if ($lockedShipment->status !== ShipmentStatus::Delivered || (float) $lockedShipment->cod_amount <= 0) {
                throw new InvalidCodRemittanceException('COD can only be reconciled for a delivered shipment with a collection amount.');
            }

            $amount = $this->calculator->toMinorUnits($data['amount']);
            $expected = $this->calculator->toMinorUnits($lockedShipment->cod_amount);
            $alreadyRemitted = $this->calculator->toMinorUnits((string) $lockedShipment->remittances()->sum('amount'));

            if ($amount <= 0 || $alreadyRemitted + $amount > $expected) {
                throw new InvalidCodRemittanceException('Remittance must be positive and cannot exceed the unreconciled COD balance.');
            }

            $payment = $this->recordPayment->execute($lockedShipment->order, [
                'amount' => $data['amount'],
                'currency' => $lockedShipment->currency->value,
                'payment_method' => PaymentMethod::CashOnDelivery->value,
                'reference' => $data['reference'] ?? $lockedShipment->tracking_number,
                'notes' => 'COD remittance from '.$lockedShipment->provider->name,
                'paid_at' => $data['remitted_at'] ?? now(),
            ], $createdBy, fromCodRemittance: true);

            $remittance = $lockedShipment->remittances()->create([
                'payment_id' => $payment->id,
                'amount' => $data['amount'],
                'currency' => $lockedShipment->currency,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'remitted_at' => $data['remitted_at'] ?? now(),
                'created_by' => $createdBy->id,
            ]);
            $newTotal = $alreadyRemitted + $amount;
            $lockedShipment->update([
                'cod_status' => $newTotal === $expected ? CodStatus::Remitted : CodStatus::PartiallyRemitted,
            ]);

            $this->recordActivity->execute(
                $lockedShipment->order,
                OrderActivityType::CodRemitted,
                sprintf('COD remittance of %s %s recorded for %s.', $lockedShipment->currency->value, number_format($amount / 100, 2), $lockedShipment->shipment_number),
                ['shipment_uuid' => $lockedShipment->uuid, 'remittance_uuid' => $remittance->uuid, 'amount' => $remittance->amount],
                $createdBy,
            );

            return $remittance->load(['shipment', 'payment', 'creator']);
        });
    }
}
