<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Customer\Models\Customer;
use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Order\Actions\CreateOrder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\Payment;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepeatOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeat_form_uses_current_catalog_and_saving_creates_an_unpaid_draft_without_stock_change(): void
    {
        $seller = $this->userWithPermissions(['orders.view', 'orders.create', 'payments.create']);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['base_price' => '10.00']);
        app(AdjustStock::class)->execute($product, null, 10, StockMovementType::StockIn, $seller);
        $original = app(CreateOrder::class)->execute([
            'customer_id' => $customer->id,
            'source' => 'facebook',
            'status' => 'new',
            'currency' => 'USD',
            'discount' => '1.00',
            'delivery_fee' => '2.00',
            'notes' => 'Old delivery instruction',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'discount' => '1.00']],
            'payment_amount' => '5.00',
            'payment_method' => 'cash',
        ], $seller);
        $product->update(['base_price' => '15.00']);

        $this->actingAs($seller)->get(route('orders.repeat', $original))
            ->assertOk()
            ->assertSeeText('Repeat as draft')
            ->assertSeeText('payments, discounts, delivery fee, and notes were not copied.')
            ->assertSee('name="status" value="draft"', false)
            ->assertViewHas('initialItems', [[
                'product_id' => $product->id,
                'product_variant_id' => '',
                'quantity' => 2,
                'discount' => '0',
            ]])
            ->assertViewHas('catalog', fn ($catalog): bool => $catalog->first()['price'] === '15.00');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);

        $this->actingAs($seller)->post(route('orders.store'), [
            'customer_id' => $customer->id,
            'source' => 'manual',
            'status' => 'draft',
            'currency' => 'USD',
            'discount' => '0',
            'delivery_fee' => '0',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'discount' => '0',
                'unit_price' => '0.01',
            ]],
        ])->assertRedirect();

        $draft = Order::query()->whereKeyNot($original->id)->with('items')->sole();
        $this->assertSame(OrderStatus::Draft, $draft->status);
        $this->assertSame('15.00', $draft->items->sole()->unit_price);
        $this->assertSame('30.00', $draft->total);
        $this->assertSame('0.00', $draft->discount);
        $this->assertSame('0.00', $draft->delivery_fee);
        $this->assertNull($draft->notes);
        $this->assertSame(0, $draft->payments()->count());
        $this->assertSame(10, $product->refresh()->stock_quantity);
        $this->assertSame('10.00', $original->items()->sole()->unit_price);
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_inactive_variant_is_omitted_and_seller_must_choose_a_replacement(): void
    {
        $seller = $this->userWithPermissions(['orders.view', 'orders.create']);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['price' => '12.00']);
        $original = app(CreateOrder::class)->execute([
            'customer_id' => $customer->id,
            'source' => 'manual',
            'status' => 'new',
            'currency' => 'USD',
            'items' => [['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 1]],
        ], $seller);
        $variant->update(['active' => false]);

        $this->actingAs($seller)->get(route('orders.repeat', $original))
            ->assertOk()
            ->assertSeeText('is no longer available in the current catalog')
            ->assertViewHas('initialItems', [[
                'product_id' => '',
                'product_variant_id' => '',
                'quantity' => 1,
                'discount' => '0',
            ]]);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_repeat_warns_when_current_stock_is_insufficient(): void
    {
        $seller = $this->userWithPermissions(['orders.view', 'orders.create']);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        app(AdjustStock::class)->execute($product, null, 1, StockMovementType::StockIn, $seller);
        $original = app(CreateOrder::class)->execute([
            'customer_id' => $customer->id,
            'source' => 'manual',
            'status' => 'new',
            'currency' => 'USD',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], $seller);

        $this->actingAs($seller)->get(route('orders.repeat', $original))
            ->assertOk()
            ->assertSeeText('has only 1 in stock');
        $this->assertSame(1, $product->refresh()->stock_quantity);
    }

    public function test_repeating_requires_both_view_and_create_permissions(): void
    {
        $creator = $this->userWithPermissions(['orders.create']);
        $viewer = $this->userWithPermissions(['orders.view']);
        $order = Order::factory()->create();

        $this->actingAs($creator)->get(route('orders.repeat', $order))->assertForbidden();
        $this->actingAs($viewer)->get(route('orders.repeat', $order))->assertForbidden();
    }
}
