<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_movements_can_be_filtered_by_product_type_and_inclusive_date(): void
    {
        $seller = $this->userWithPermissions(['inventory.view', 'products.view']);
        $product = Product::factory()->create(['name' => 'Audit Product']);
        $other = Product::factory()->create(['name' => 'Other Product']);
        $adjust = app(AdjustStock::class);

        $this->travelTo(CarbonImmutable::parse('2026-10-10 10:00:00'));
        $adjust->execute($product, null, 5, StockMovementType::StockIn, $seller, notes: 'Initial receipt');
        $this->travelTo(CarbonImmutable::parse('2026-10-11 23:59:59'));
        $adjust->execute($product, null, 2, StockMovementType::StockOut, $seller, notes: 'Selected outgoing movement');
        $adjust->execute($other, null, 2, StockMovementType::StockIn, $seller, notes: 'Other receipt');
        $this->travelBack();

        $this->actingAs($seller)->get(route('inventory.index', [
            'product' => $product->uuid,
            'type' => StockMovementType::StockOut->value,
            'created_from' => '2026-10-11',
            'created_to' => '2026-10-11',
        ]))->assertOk()
            ->assertSeeText('Selected outgoing movement')
            ->assertDontSeeText('Initial receipt')
            ->assertDontSeeText('Other receipt');
    }

    public function test_search_finds_variant_sku_and_product_page_links_to_scoped_history(): void
    {
        $seller = $this->userWithPermissions(['inventory.view', 'products.view']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SPECIAL-VAR-001']);
        $sibling = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SIBLING-VAR-002']);
        $other = Product::factory()->create();
        app(AdjustStock::class)->execute($product, $variant, 3, StockMovementType::StockIn, $seller, notes: 'Variant receipt');
        app(AdjustStock::class)->execute($product, $sibling, 1, StockMovementType::StockIn, $seller, notes: 'Sibling receipt');
        app(AdjustStock::class)->execute($other, null, 2, StockMovementType::StockIn, $seller, notes: 'Unrelated receipt');

        $this->actingAs($seller)->get(route('inventory.index', ['search' => 'SPECIAL-VAR-001']))
            ->assertOk()
            ->assertSeeText('Variant receipt')
            ->assertDontSeeText('Sibling receipt')
            ->assertDontSeeText('Unrelated receipt');

        $this->actingAs($seller)->get(route('inventory.index', ['product' => $product->uuid, 'variant' => $variant->uuid]))
            ->assertOk()
            ->assertSeeText('Variant receipt')
            ->assertDontSeeText('Sibling receipt');

        $this->actingAs($seller)->get(route('products.show', $product))
            ->assertOk()
            ->assertSee(route('inventory.index', ['product' => $product->uuid]))
            ->assertSee(str_replace('&', '&amp;', route('inventory.index', ['product' => $product->uuid, 'variant' => $variant->uuid])), false);
    }

    public function test_invalid_filters_redirect_to_unfiltered_history(): void
    {
        $seller = $this->userWithPermissions(['inventory.view']);

        $this->actingAs($seller)->get(route('inventory.index', [
            'type' => 'unknown',
            'created_from' => '2026-10-11',
            'created_to' => '2026-10-10',
        ]))->assertRedirect(route('inventory.index'))
            ->assertSessionHasErrors(['type', 'created_to']);
    }

    public function test_inventory_view_is_required_and_does_not_imply_adjust_permission(): void
    {
        $viewer = $this->userWithPermissions(['inventory.view']);
        $product = Product::factory()->create(['name' => 'Restricted Product']);
        app(AdjustStock::class)->execute($product, null, 1, StockMovementType::StockIn, $viewer);

        $this->actingAs($viewer)->get(route('inventory.index'))
            ->assertOk()
            ->assertSeeText($product->name)
            ->assertDontSeeText('Adjust stock')
            ->assertDontSee(route('products.show', $product));

        $this->actingAs(User::factory()->create())->get(route('inventory.index'))->assertForbidden();
    }
}
