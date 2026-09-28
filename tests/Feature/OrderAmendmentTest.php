<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Customer\Models\Customer;
use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Actions\CreateOrder;
use App\Domain\Order\Actions\UpdateOrder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Exceptions\InvalidOrderTotalException;
use App\Domain\Order\Exceptions\OrderCannotBeAmendedException;
use App\Domain\Payment\Actions\RecordPayment;
use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAmendmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_order_can_be_amended_with_fresh_server_prices(): void
    {
        [$user, $order, $product, $customer] = $this->newOrder();
        $product->update(['base_price' => '12.00', 'active' => false]);

        $this->actingAs($user)->get(route('orders.edit', $order))
            ->assertOk()
            ->assertSee('Amend order')
            ->assertSee($order->order_number);

        $this->actingAs($user)->put(route('orders.update', $order), [
            'customer_id' => $customer->id,
            'source' => 'messenger',
            'status' => 'new',
            'currency' => 'USD',
            'discount' => '1.00',
            'delivery_fee' => '2.00',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'discount' => '0',
                'unit_price' => '0.01',
            ]],
        ])->assertRedirect(route('orders.show', $order));

        $order->refresh()->load('items');
        $this->assertSame('12.00', $order->items->sole()->unit_price);
        $this->assertSame('25.00', $order->total);
        $this->assertSame('messenger', $order->source->value);
        $this->assertSame(10, $product->refresh()->stock_quantity);
    }

    public function test_confirmed_order_cannot_be_amended(): void
    {
        [$user, $order, $product, $customer] = $this->newOrder();
        app(ChangeOrderStatus::class)->execute($order, OrderStatus::Confirmed, $user);

        $this->expectException(OrderCannotBeAmendedException::class);

        app(UpdateOrder::class)->execute($order, [
            'customer_id' => $customer->id,
            'source' => 'manual',
            'status' => 'new',
            'currency' => 'USD',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'discount' => '0']],
        ]);
    }

    public function test_amended_total_cannot_drop_below_recorded_payments(): void
    {
        [$user, $order, $product, $customer] = $this->newOrder(quantity: 2);
        app(RecordPayment::class)->execute($order, [
            'amount' => '15.00',
            'currency' => 'USD',
            'payment_method' => 'cash',
        ], $user);

        $this->expectException(InvalidOrderTotalException::class);

        app(UpdateOrder::class)->execute($order, [
            'customer_id' => $customer->id,
            'source' => 'manual',
            'status' => 'new',
            'currency' => 'USD',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'discount' => '0']],
        ]);
    }

    private function newOrder(int $quantity = 1): array
    {
        $user = $this->userWithPermissions(['orders.create', 'orders.view', 'orders.update', 'payments.create']);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['base_price' => '10.00']);
        app(AdjustStock::class)->execute($product, null, 10, StockMovementType::StockIn, $user);
        $order = app(CreateOrder::class)->execute([
            'customer_id' => $customer->id,
            'source' => 'manual',
            'status' => 'new',
            'currency' => 'USD',
            'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'discount' => '0']],
        ], $user);

        return [$user, $order, $product, $customer];
    }
}
