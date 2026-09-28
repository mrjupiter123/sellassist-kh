<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Customer\Models\Customer;
use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Actions\CreateOrder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\OrderReturn\Actions\CreateOrderReturn;
use App\Domain\OrderReturn\Exceptions\InvalidOrderReturnException;
use App\Domain\OrderReturn\Models\OrderReturn;
use App\Domain\Payment\Actions\RecordPayment;
use App\Domain\Payment\Actions\RecordRefund;
use App\Domain\Payment\Exceptions\InvalidRefundAmountException;
use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnRefundWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_restock_and_refund_are_recorded_atomically(): void
    {
        [$user, $order, $product, $payment] = $this->completedPaidOrder();
        $item = $order->items()->sole();

        $this->actingAs($user)->post(route('orders.returns.store', $order), [
            'reason' => 'Wrong size',
            'items' => [[
                'order_item_id' => $item->id,
                'quantity' => 1,
                'restock' => 1,
            ]],
            'payment_id' => $payment->id,
            'refund_amount' => '10.00',
            'refund_method' => 'cash',
        ])->assertRedirect();

        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertDatabaseHas('order_return_items', ['order_item_id' => $item->id, 'quantity' => 1, 'restock' => 1]);
        $this->assertDatabaseHas('refunds', ['payment_id' => $payment->id, 'amount' => 10]);
        $this->assertDatabaseHas('stock_movements', ['type' => 'return', 'quantity' => 1, 'reference_type' => 'order_return']);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->refresh()->payment_status);

        $orderReturn = OrderReturn::query()->sole();
        $this->actingAs($user)->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee($orderReturn->return_number)
            ->assertSee('Refund ledger');
    }

    public function test_return_without_restock_does_not_change_inventory(): void
    {
        [$user, $order, $product] = $this->completedPaidOrder();
        $item = $order->items()->sole();

        app(CreateOrderReturn::class)->execute($order, [
            'reason' => 'Damaged',
            'items' => [['order_item_id' => $item->id, 'quantity' => 1, 'restock' => false]],
        ], $user);

        $this->assertSame(7, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_cumulative_returns_cannot_exceed_ordered_quantity(): void
    {
        [$user, $order, $product] = $this->completedPaidOrder();
        $item = $order->items()->sole();
        $action = app(CreateOrderReturn::class);
        $action->execute($order, [
            'items' => [['order_item_id' => $item->id, 'quantity' => 2, 'restock' => true]],
        ], $user);

        try {
            $action->execute($order, [
                'items' => [['order_item_id' => $item->id, 'quantity' => 2, 'restock' => true]],
            ], $user);
            $this->fail('An excessive return should fail.');
        } catch (InvalidOrderReturnException) {
            $this->assertDatabaseCount('order_returns', 1);
            $this->assertSame(9, $product->refresh()->stock_quantity);
        }
    }

    public function test_full_refund_marks_order_refunded(): void
    {
        [$user, $order, , $payment] = $this->completedPaidOrder();

        app(RecordRefund::class)->execute($order, $payment, [
            'amount' => '30.00',
            'refund_method' => 'aba',
        ], $user);

        $this->assertSame(PaymentStatus::Refunded, $order->refresh()->payment_status);
        $this->assertSame('30.00', $order->refunds()->sole()->amount);
    }

    public function test_refund_cannot_exceed_selected_payment_balance(): void
    {
        [$user, $order, , $payment] = $this->completedPaidOrder();

        $this->expectException(InvalidRefundAmountException::class);

        try {
            app(RecordRefund::class)->execute($order, $payment, [
                'amount' => '30.01',
                'refund_method' => 'cash',
            ], $user);
        } finally {
            $this->assertDatabaseCount('refunds', 0);
        }
    }

    private function completedPaidOrder(): array
    {
        $user = $this->userWithPermissions([
            'orders.create', 'orders.view', 'orders.update',
            'payments.create', 'payments.view', 'payments.refund',
            'returns.view', 'returns.create',
        ]);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['base_price' => '10.00']);
        app(AdjustStock::class)->execute($product, null, 10, StockMovementType::StockIn, $user);
        $order = app(CreateOrder::class)->execute([
            'customer_id' => $customer->id,
            'source' => 'manual',
            'status' => 'new',
            'currency' => 'USD',
            'items' => [['product_id' => $product->id, 'quantity' => 3, 'discount' => '0']],
        ], $user);

        foreach ([OrderStatus::Confirmed, OrderStatus::Packed, OrderStatus::Shipped, OrderStatus::Completed] as $status) {
            $order = app(ChangeOrderStatus::class)->execute($order, $status, $user);
        }

        $payment = app(RecordPayment::class)->execute($order, [
            'amount' => '30.00',
            'currency' => 'USD',
            'payment_method' => 'aba',
        ], $user);

        return [$user, $order, $product, $payment];
    }
}
