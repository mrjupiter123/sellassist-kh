<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockWorklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_list_includes_only_active_sellable_items_at_or_below_threshold(): void
    {
        $seller = $this->userWithPermissions(['inventory.view', 'products.view', 'orders.view']);
        $standalone = Product::factory()->create(['name' => 'Standalone Low', 'stock_quantity' => 0]);
        $variantProduct = Product::factory()->create(['name' => 'Variant Parent']);
        $variant = ProductVariant::factory()->create(['product_id' => $variantProduct->id, 'sku' => 'LOW-VARIANT']);
        $inactiveProduct = Product::factory()->create(['name' => 'Inactive Parent', 'active' => false]);
        ProductVariant::factory()->create(['product_id' => $inactiveProduct->id]);
        ProductVariant::factory()->create(['product_id' => $variantProduct->id, 'sku' => 'INACTIVE-VARIANT', 'active' => false]);
        $healthy = Product::factory()->create(['name' => 'Healthy Stock']);
        app(AdjustStock::class)->execute($healthy, null, 10, StockMovementType::StockIn, $seller);

        $this->actingAs($seller)->get(route('inventory.low-stock'))
            ->assertOk()
            ->assertSeeText($standalone->name)
            ->assertSeeText($variant->sku)
            ->assertDontSeeText($inactiveProduct->name)
            ->assertDontSeeText('INACTIVE-VARIANT')
            ->assertDontSeeText($healthy->name);

        $this->actingAs($seller)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText($standalone->name)
            ->assertSeeText($variantProduct->name)
            ->assertDontSeeText($inactiveProduct->name);
    }

    public function test_low_stock_product_list_is_paginated(): void
    {
        $seller = $this->userWithPermissions(['inventory.view', 'products.view']);
        $products = Product::factory()->count(21)->create(['stock_quantity' => 0]);

        $this->actingAs($seller)->get(route('inventory.low-stock'))
            ->assertOk()
            ->assertDontSeeText($products->last()->name);

        $this->actingAs($seller)->get(route('inventory.low-stock', ['products_page' => 2]))
            ->assertOk()
            ->assertSeeText($products->last()->name);
    }

    public function test_inventory_view_permission_is_required_and_dashboard_hides_low_stock_without_it(): void
    {
        $orderViewer = $this->userWithPermissions(['orders.view']);
        $this->actingAs($orderViewer)->get(route('inventory.low-stock'))->assertForbidden();
        $this->actingAs($orderViewer)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('inventory.low-stock'));
        $this->actingAs(User::factory()->create())->get(route('inventory.low-stock'))->assertForbidden();
    }
}
