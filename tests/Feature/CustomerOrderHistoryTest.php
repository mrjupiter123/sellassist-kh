<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_seller_can_page_through_only_this_customers_orders(): void
    {
        $seller = $this->userWithPermissions(['customers.view', 'orders.view']);
        $customer = Customer::factory()->create();
        $orders = Order::factory()->count(16)->create([
            'customer_id' => $customer->id,
            'created_by' => $seller->id,
            'created_at' => '2026-10-10 10:00:00',
        ]);
        $other = Order::factory()->create(['created_by' => $seller->id]);

        $this->actingAs($seller)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSeeText('Order history')
            ->assertSeeText($orders->last()->order_number)
            ->assertDontSeeText($orders->first()->order_number)
            ->assertDontSeeText($other->order_number);

        $this->actingAs($seller)->get(route('customers.show', [$customer, 'page' => 2]))
            ->assertOk()
            ->assertSeeText($orders->first()->order_number)
            ->assertDontSeeText($orders->last()->order_number)
            ->assertDontSeeText($other->order_number);
    }

    public function test_customer_viewer_without_order_permission_cannot_see_order_history(): void
    {
        $viewer = $this->userWithPermissions(['customers.view']);
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($viewer)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSeeText($customer->name)
            ->assertDontSeeText('Order history')
            ->assertDontSeeText($order->order_number);
    }

    public function test_order_viewer_without_customer_permission_cannot_open_customer_page(): void
    {
        $viewer = $this->userWithPermissions(['orders.view']);
        $customer = Customer::factory()->create();

        $this->actingAs($viewer)->get(route('customers.show', $customer))->assertForbidden();
    }
}
