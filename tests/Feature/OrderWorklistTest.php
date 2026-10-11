<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Customer\Enums\CustomerSource;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWorklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_combine_operational_filters_and_date_range(): void
    {
        $seller = $this->userWithPermissions(['orders.view']);
        $matching = Order::factory()->create([
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'source' => CustomerSource::Messenger,
            'currency' => 'KHR',
            'created_at' => '2026-10-10 23:59:59',
        ]);
        $outsideDate = Order::factory()->create([
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'source' => CustomerSource::Messenger,
            'currency' => 'KHR',
            'created_at' => '2026-10-11 00:00:00',
        ]);
        $wrongPayment = Order::factory()->create([
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'source' => CustomerSource::Messenger,
            'currency' => 'KHR',
            'created_at' => '2026-10-10 10:00:00',
        ]);

        $this->actingAs($seller)->get(route('orders.index', [
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'source' => 'messenger',
            'currency' => 'KHR',
            'created_from' => '2026-10-10',
            'created_to' => '2026-10-10',
        ]))->assertOk()
            ->assertSeeText($matching->order_number)
            ->assertDontSeeText($outsideDate->order_number)
            ->assertDontSeeText($wrongPayment->order_number);
    }

    public function test_search_finds_historical_customer_snapshot_and_phone(): void
    {
        $seller = $this->userWithPermissions(['orders.view']);
        $matching = Order::factory()->create([
            'customer_name' => 'Historical Buyer',
            'customer_phone' => '012345678',
        ]);
        $other = Order::factory()->create();

        foreach (['Historical Buyer', '012345678', $matching->order_number] as $term) {
            $this->actingAs($seller)->get(route('orders.index', ['search' => $term]))
                ->assertOk()
                ->assertSeeText($matching->order_number)
                ->assertDontSeeText($other->order_number);
        }
    }

    public function test_invalid_filters_redirect_to_clean_worklist_instead_of_looping(): void
    {
        $seller = $this->userWithPermissions(['orders.view']);

        $this->actingAs($seller)->get(route('orders.index', [
            'status' => 'unknown',
            'created_from' => '2026-10-11',
            'created_to' => '2026-10-10',
        ]))->assertRedirect(route('orders.index'))
            ->assertSessionHasErrors(['status', 'created_to']);
    }

    public function test_user_without_order_view_permission_cannot_open_worklist(): void
    {
        $this->actingAs(User::factory()->create())->get(route('orders.index'))->assertForbidden();
    }
}
