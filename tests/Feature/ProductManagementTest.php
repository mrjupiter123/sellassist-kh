<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_creation_records_initial_stock_movement(): void
    {
        $user = $this->userWithPermissions(['products.create', 'products.view']);

        $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Oversize T-Shirt',
            'sku' => 'TS-001',
            'base_price' => '12.50',
            'initial_stock' => 20,
            'low_stock_threshold' => 5,
            'active' => 1,
        ])->assertRedirect();

        $product = Product::query()->sole();
        $movement = StockMovement::query()->sole();
        $this->assertSame(20, $product->stock_quantity);
        $this->assertSame(StockMovementType::StockIn, $movement->type);
        $this->assertSame(20, $movement->quantity_after);
    }

    public function test_variant_creation_uses_its_own_stock_and_price_override(): void
    {
        $user = $this->userWithPermissions(['products.create', 'products.view']);
        $product = Product::factory()->create(['base_price' => '10.00']);

        $this->actingAs($user)->post(route('products.variants.store', $product), [
            'sku' => 'TS-BLK-M',
            'color' => 'Black',
            'size' => 'M',
            'price' => '11.50',
            'cost' => '5.00',
            'initial_stock' => 7,
            'low_stock_threshold' => 2,
            'active' => 1,
        ])->assertRedirect(route('products.show', $product));

        $variant = ProductVariant::query()->sole();
        $this->assertSame('11.50', $variant->price);
        $this->assertSame(7, $variant->stock_quantity);
        $this->assertSame('Black / M', $variant->display_name);
    }

    public function test_variant_can_be_edited_and_deactivated_without_changing_stock(): void
    {
        $user = $this->userWithPermissions(['products.update', 'products.view']);
        $variant = ProductVariant::factory()->create(['stock_quantity' => 8]);

        $this->actingAs($user)->put(route('products.variants.update', [$variant->product, $variant]), [
            'sku' => 'UPDATED-SKU',
            'color' => 'Navy',
            'size' => 'XL',
            'price' => '14.00',
            'cost' => '6.00',
            'low_stock_threshold' => 3,
            'active' => 0,
        ])->assertRedirect(route('products.show', $variant->product));

        $variant->refresh();
        $this->assertFalse($variant->active);
        $this->assertSame(8, $variant->stock_quantity);
        $this->assertSame('Navy / XL', $variant->display_name);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
