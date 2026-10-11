<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Actions\ExportStockMovements;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class StockMovementExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_uses_filters_escapes_formulas_and_omits_notes(): void
    {
        $seller = $this->userWithPermissions(['inventory.view']);
        $product = Product::factory()->create(['name' => '+អាវយឺត']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'EXPORT-VARIANT']);
        $sibling = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'OTHER-VARIANT']);
        $adjust = app(AdjustStock::class);
        $adjust->execute($product, $variant, 3, StockMovementType::StockIn, $seller, notes: 'Private adjustment note');
        $adjust->execute($product, $sibling, 2, StockMovementType::StockIn, $seller);

        $response = $this->actingAs($seller)->get(route('inventory.export', [
            'product' => $product->uuid,
            'variant' => $variant->uuid,
            'type' => StockMovementType::StockIn->value,
        ]));

        $response->assertOk()->assertDownload()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'+អាវយឺត", $csv);
        $this->assertStringContainsString('EXPORT-VARIANT', $csv);
        $this->assertStringNotContainsString('OTHER-VARIANT', $csv);
        $this->assertStringNotContainsString('Private adjustment note', $csv);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertSame(3, $variant->refresh()->stock_quantity);
    }

    public function test_export_requires_inventory_view_permission(): void
    {
        $this->actingAs(User::factory()->create())->get(route('inventory.export'))->assertForbidden();
    }

    public function test_negative_stock_movement_is_exported_as_a_number(): void
    {
        $seller = $this->userWithPermissions(['inventory.view']);
        $product = Product::factory()->create();
        $adjust = app(AdjustStock::class);
        $adjust->execute($product, null, 3, StockMovementType::StockIn, $seller);
        $adjust->execute($product, null, 2, StockMovementType::StockOut, $seller);

        $csv = $this->actingAs($seller)->get(route('inventory.export', ['type' => StockMovementType::StockOut->value]))
            ->assertOk()
            ->streamedContent();
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));
        $row = str_getcsv($lines[1]);

        $this->assertSame('-2', $row[6]);
        $this->assertSame('3', $row[7]);
        $this->assertSame('1', $row[8]);
    }

    public function test_invalid_filters_redirect_to_inventory_without_streaming(): void
    {
        $seller = $this->userWithPermissions(['inventory.view']);

        $this->actingAs($seller)->get(route('inventory.export', ['type' => 'invalid']))
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHasErrors('type');
    }

    public function test_oversized_export_returns_to_filtered_history(): void
    {
        $seller = $this->userWithPermissions(['inventory.view']);
        $this->mock(ExportStockMovements::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('isWithinLimit')->once()->andReturn(false));

        $this->actingAs($seller)->get(route('inventory.export', ['type' => StockMovementType::Order->value]))
            ->assertRedirect(route('inventory.index', ['type' => StockMovementType::Order->value]))
            ->assertSessionHasErrors('export');
    }
}
