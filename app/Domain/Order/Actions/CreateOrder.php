<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Events\OrderCreated;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderCalculator;
use App\Domain\Order\Services\OrderItemResolver;
use App\Domain\OrderActivity\Enums\OrderActivityType;
use App\Domain\OrderActivity\Services\RecordOrderActivity;
use App\Domain\Payment\Actions\RecordPayment;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateOrder
{
    public function __construct(
        private readonly OrderCalculator $calculator,
        private readonly OrderItemResolver $itemResolver,
        private readonly RecordPayment $recordPayment,
        private readonly RecordOrderActivity $recordActivity,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(array $data, User $createdBy): Order
    {
        return DB::transaction(function () use ($data, $createdBy): Order {
            $customer = Customer::query()->findOrFail($data['customer_id']);
            $resolvedItems = $this->itemResolver->resolve($data['items']);

            $totals = $this->calculator->calculate(
                array_map(fn (array $item): array => [
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                ], $resolvedItems),
                $data['discount'] ?? '0',
                $data['delivery_fee'] ?? '0',
            );

            $order = Order::query()->create([
                'order_number' => 'PENDING-'.Str::uuid(),
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'shipping_address' => $customer->fullAddress(),
                'source' => $data['source'],
                'status' => $data['status'] ?? OrderStatus::New->value,
                'payment_status' => PaymentStatus::Unpaid,
                'currency' => $data['currency'],
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'delivery_fee' => $totals['delivery_fee'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy->id,
            ]);

            $order->update([
                'order_number' => 'ORD-'.$order->created_at->format('Ymd').'-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT),
            ]);

            foreach ($resolvedItems as $index => $item) {
                /** @var Product $product */
                $product = $item['product'];
                /** @var ProductVariant|null $variant */
                $variant = $item['variant'];

                $order->items()->create([
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

            $this->recordActivity->execute(
                $order,
                OrderActivityType::Created,
                'Order created.',
                ['order_number' => $order->order_number],
                $createdBy,
            );

            if (isset($data['payment_amount']) && $this->calculator->toMinorUnits($data['payment_amount']) > 0) {
                $this->recordPayment->execute($order, [
                    'amount' => $data['payment_amount'],
                    'currency' => $data['currency'],
                    'payment_method' => $data['payment_method'],
                    'reference' => $data['payment_reference'] ?? null,
                    'notes' => 'Payment recorded during order creation',
                    'paid_at' => now(),
                ], $createdBy);
            }

            DB::afterCommit(fn () => OrderCreated::dispatch($order));

            return $order->load(['customer', 'items.product', 'items.variant', 'payments']);
        });
    }
}
