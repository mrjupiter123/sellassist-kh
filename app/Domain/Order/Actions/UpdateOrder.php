<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Exceptions\InvalidOrderTotalException;
use App\Domain\Order\Exceptions\OrderCannotBeAmendedException;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderCalculator;
use App\Domain\Order\Services\OrderItemResolver;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

final class UpdateOrder
{
    public function __construct(
        private readonly OrderCalculator $calculator,
        private readonly OrderItemResolver $itemResolver,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            $lockedOrder = Order::query()->with('payments')->lockForUpdate()->findOrFail($order->id);

            if (! $this->canExecute($lockedOrder)) {
                throw new OrderCannotBeAmendedException('Only draft or new orders can be amended.');
            }

            Customer::query()->findOrFail($data['customer_id']);
            $resolvedItems = $this->itemResolver->resolve($data['items'], activeOnly: false);
            $totals = $this->calculator->calculate(
                array_map(fn (array $item): array => [
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                ], $resolvedItems),
                $data['discount'] ?? '0',
                $data['delivery_fee'] ?? '0',
            );

            $paid = $this->calculator->toMinorUnits((string) $lockedOrder->payments->sum('amount'));
            $newTotal = $this->calculator->toMinorUnits($totals['total']);

            if ($paid > 0 && $lockedOrder->currency->value !== $data['currency']) {
                throw new InvalidOrderTotalException('The currency cannot change after a payment has been recorded.');
            }

            if ($paid > $newTotal) {
                throw new InvalidOrderTotalException('The amended total cannot be lower than payments already recorded.');
            }

            $lockedOrder->update([
                'customer_id' => $data['customer_id'],
                'source' => $data['source'],
                'status' => $data['status'],
                'currency' => $data['currency'],
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'delivery_fee' => $totals['delivery_fee'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
            ]);

            $lockedOrder->items()->delete();

            foreach ($resolvedItems as $index => $item) {
                /** @var Product $product */
                $product = $item['product'];
                /** @var ProductVariant|null $variant */
                $variant = $item['variant'];

                $lockedOrder->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->display_name,
                    'sku' => $variant?->sku ?? $product->sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                    'line_total' => $totals['lines'][$index],
                ]);
            }

            return $lockedOrder->refresh()->load(['customer', 'items.product', 'items.variant', 'payments']);
        });
    }

    public function canExecute(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Draft, OrderStatus::New], true);
    }
}
