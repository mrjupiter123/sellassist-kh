<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Customer\Models\Customer;
use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Order\Actions\CreateOrder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Exceptions\InvalidPaymentAmountException;
use App\Domain\Product\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_uses_database_price_and_calculates_totals_server_side(): void
    {
        $user = $this->userWithPermissions(['orders.create', 'orders.view']);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['base_price' => '12.50']);
        $this->stock($product, 10, $user);

        $response = $this->actingAs($user)->post(route('orders.store'), [
            'customer_id' => $customer->id,
            'source' => 'facebook_live',
            'status' => 'new',
            'currency' => 'USD',
            'discount' => '3.00',
            'delivery_fee' => '2.00',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'discount' => '1.00',
                'unit_price' => '0.01',
            ]],
        ]);

        $order = Order::query()->with('items')->sole();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4,}$/', $order->order_number);
        $this->assertSame('12.50', $order->items->sole()->unit_price);
        $this->assertSame('24.00', $order->subtotal);
        $this->assertSame('23.00', $order->total);
        $this->assertSame(10, $product->refresh()->stock_quantity);
    }

    public function test_confirming_order_deducts_stock(): void
    {
        [$user, $order, $product] = $this->newOrder(quantity: 3, stock: 10);

        $this->actingAs($user)->patch(route('orders.status.update', $order), ['status' => 'confirmed'])
            ->assertRedirect();

        $this->assertSame(7, $product->refresh()->stock_quantity);
        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'type' => StockMovementType::Order->value,
            'quantity' => -3,
        ]);
    }

    public function test_confirming_twice_does_not_deduct_stock_twice(): void
    {
        [$user, $order, $product] = $this->newOrder(quantity: 2, stock: 10);

        $this->actingAs($user)->patch(route('orders.status.update', $order), ['status' => 'confirmed']);
        $this->actingAs($user)->patch(route('orders.status.update', $order), ['status' => 'confirmed'])
            ->assertRedirect();

        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertSame(1, StockMovement::query()->where('reference_type', 'order')->where('reference_id', $order->id)->where('type', 'order')->count());
    }

    public function test_cancelling_confirmed_order_restores_stock_once(): void
    {
        [$user, $order, $product] = $this->newOrder(quantity: 4, stock: 10);
        $this->actingAs($user)->patch(route('orders.status.update', $order), ['status' => 'confirmed']);

        $this->actingAs($user)->patch(route('orders.status.update', $order), ['status' => 'cancelled'])
            ->assertRedirect();
        $this->actingAs($user)->patch(route('orders.status.update', $order), ['status' => 'cancelled'])
            ->assertRedirect();

        $this->assertSame(10, $product->refresh()->stock_quantity);
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(1, StockMovement::query()->where('reference_id', $order->id)->where('type', 'return')->count());
    }

    public function test_recording_partial_payment_updates_payment_status(): void
    {
        [$user, $order] = $this->newOrder(quantity: 2, stock: 10);

        $this->actingAs($user)->post(route('orders.payments.store', $order), [
            'amount' => '10.00',
            'currency' => 'USD',
            'payment_method' => 'aba',
        ])->assertRedirect();

        $this->assertSame(PaymentStatus::PartiallyPaid, $order->refresh()->payment_status);
        $this->assertSame('10.00', $order->payments()->sole()->amount);
    }

    public function test_recording_payments_up_to_full_total_marks_order_paid(): void
    {
        [$user, $order] = $this->newOrder(quantity: 2, stock: 10);

        $this->actingAs($user)->post(route('orders.payments.store', $order), [
            'amount' => '10.00', 'currency' => 'USD', 'payment_method' => 'cash',
        ]);
        $this->actingAs($user)->post(route('orders.payments.store', $order), [
            'amount' => '10.00', 'currency' => 'USD', 'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
        $this->assertSame(20.0, (float) $order->payments()->sum('amount'));
    }

    public function test_overpayment_is_rejected_without_creating_payment(): void
    {
        [$user, $order] = $this->newOrder(quantity: 1, stock: 10);

        $this->withoutExceptionHandling();
        $this->expectException(InvalidPaymentAmountException::class);

        try {
            $this->actingAs($user)->post(route('orders.payments.store', $order), [
                'amount' => '10.01', 'currency' => 'USD', 'payment_method' => 'cash',
            ]);
        } finally {
            $this->assertDatabaseCount('payments', 0);
        }
    }

    /** @return array{User, Order, Product} */
    private function newOrder(int $quantity, int $stock): array
    {
        $user = $this->userWithPermissions([
            'orders.create', 'orders.view', 'orders.update', 'orders.cancel', 'payments.create',
        ]);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['base_price' => '10.00']);
        $this->stock($product, $stock, $user);

        $order = app(CreateOrder::class)->execute([
            'customer_id' => $customer->id,
            'source' => 'manual',
            'status' => 'new',
            'currency' => 'USD',
            'discount' => '0',
            'delivery_fee' => '0',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => $quantity,
                'discount' => '0',
            ]],
        ], $user);

        return [$user, $order, $product];
    }

    private function stock(Product $product, int $quantity, User $user): void
    {
        app(AdjustStock::class)->execute($product, null, $quantity, StockMovementType::StockIn, $user);
    }
}
