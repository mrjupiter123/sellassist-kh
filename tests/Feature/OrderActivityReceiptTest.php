<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Customer\Models\Customer;
use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Actions\CreateOrder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\OrderActivity\Enums\OrderActivityType;
use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderActivityReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_workflows_record_transactional_order_activity(): void
    {
        $user = $this->userWithPermissions(['orders.view', 'orders.update']);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['base_price' => '10.00']);
        app(AdjustStock::class)->execute($product, null, 5, StockMovementType::StockIn, $user);
        $order = app(CreateOrder::class)->execute($this->orderData($customer, $product), $user);

        app(ChangeOrderStatus::class)->execute($order, OrderStatus::Confirmed, $user);

        $this->assertDatabaseCount('order_activities', 2);
        $this->assertDatabaseHas('order_activities', ['order_id' => $order->id, 'type' => OrderActivityType::Created->value]);
        $this->assertDatabaseHas('order_activities', ['order_id' => $order->id, 'type' => OrderActivityType::StatusChanged->value]);
        $this->actingAs($user)->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Activity timeline')
            ->assertSee('Status changed from New to Confirmed.');
    }

    public function test_receipt_uses_customer_snapshot_after_customer_changes(): void
    {
        $user = $this->userWithPermissions(['orders.view']);
        $customer = Customer::factory()->create([
            'name' => 'Original Customer',
            'phone' => '012345678',
            'address' => 'Street 1',
            'commune' => 'Commune A',
            'district' => 'District B',
            'province' => 'Phnom Penh',
        ]);
        $product = Product::factory()->create(['base_price' => '10.00']);
        $order = app(CreateOrder::class)->execute($this->orderData($customer, $product), $user);
        $customer->update(['name' => 'Changed Customer', 'phone' => '099999999']);

        $this->actingAs($user)->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertSee('Receipt / បង្កាន់ដៃ')
            ->assertSee('Original Customer')
            ->assertSee('012345678')
            ->assertSee('Street 1, Commune A, District B, Phnom Penh')
            ->assertDontSee('Changed Customer');
    }

    /** @return array<string, mixed> */
    private function orderData(Customer $customer, Product $product): array
    {
        return [
            'customer_id' => $customer->id,
            'source' => 'manual',
            'status' => 'new',
            'currency' => 'USD',
            'discount' => '0',
            'delivery_fee' => '0',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'discount' => '0',
            ]],
        ];
    }
}
