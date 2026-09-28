<?php

declare(strict_types=1);

namespace App\Domain\Order\Services;

use App\Domain\Order\Exceptions\InvalidOrderTotalException;

final class OrderCalculator
{
    /**
     * @param  array<int, array{quantity: int, unit_price: int|float|string, discount?: int|float|string}>  $items
     * @return array{subtotal: string, discount: string, delivery_fee: string, total: string, lines: array<int, string>}
     */
    public function calculate(array $items, int|float|string $orderDiscount = '0', int|float|string $deliveryFee = '0'): array
    {
        if ($items === []) {
            throw new InvalidOrderTotalException('An order must contain at least one item.');
        }

        $subtotal = 0;
        $lines = [];

        foreach ($items as $index => $item) {
            $quantity = (int) $item['quantity'];

            if ($quantity < 1) {
                throw new InvalidOrderTotalException('Order item quantities must be at least one.');
            }

            $unitPrice = $this->toMinorUnits($item['unit_price']);
            $lineDiscount = $this->toMinorUnits($item['discount'] ?? '0');
            $gross = $quantity * $unitPrice;

            if ($lineDiscount > $gross) {
                throw new InvalidOrderTotalException('An item discount cannot exceed its gross amount.');
            }

            $lineTotal = $gross - $lineDiscount;
            $subtotal += $lineTotal;
            $lines[$index] = $this->formatMinorUnits($lineTotal);
        }

        $discount = $this->toMinorUnits($orderDiscount);
        $delivery = $this->toMinorUnits($deliveryFee);

        if ($discount > $subtotal) {
            throw new InvalidOrderTotalException('The order discount cannot exceed the subtotal.');
        }

        $total = $subtotal - $discount + $delivery;

        if ($total < 0) {
            throw new InvalidOrderTotalException('The order total cannot be negative.');
        }

        return [
            'subtotal' => $this->formatMinorUnits($subtotal),
            'discount' => $this->formatMinorUnits($discount),
            'delivery_fee' => $this->formatMinorUnits($delivery),
            'total' => $this->formatMinorUnits($total),
            'lines' => $lines,
        ];
    }

    public function toMinorUnits(int|float|string $amount): int
    {
        $normalized = trim((string) $amount);

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new InvalidOrderTotalException('Money amounts must be non-negative with no more than two decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function formatMinorUnits(int $amount): string
    {
        return sprintf('%d.%02d', intdiv($amount, 100), $amount % 100);
    }
}
