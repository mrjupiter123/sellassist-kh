<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Actions\RecordStockCount;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Exceptions\InvalidStockCountException;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockCountWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_physical_count_records_discrepancy_and_one_audited_stock_change(): void
    {
        $seller = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();
        $token = (string) Str::uuid();

        $this->actingAs($seller)->post(route('inventory.stock-counts.store'), [
            'uuid' => $token,
            'product_id' => $product->id,
            'observed_quantity' => 0,
            'counted_quantity' => 7,
            'notes' => 'Shelf count',
        ])->assertRedirect(route('inventory.stock-counts.index'));

        $count = StockCount::query()->sole();
        $movement = StockMovement::query()->sole();
        $this->assertSame($token, $count->uuid);
        $this->assertSame(0, $count->expected_quantity);
        $this->assertSame(7, $count->counted_quantity);
        $this->assertSame(7, $count->difference);
        $this->assertSame(7, $product->refresh()->stock_quantity);
        $this->assertSame(StockMovementType::Adjustment, $movement->type);
        $this->assertSame('stock_count', $movement->reference_type);
        $this->assertSame($count->id, $movement->reference_id);
        $this->assertSame($seller->id, $movement->created_by);

        $this->actingAs($seller)->post(route('inventory.stock-counts.store'), [
            'uuid' => $token,
            'product_id' => $product->id,
            'observed_quantity' => 0,
            'counted_quantity' => 7,
            'notes' => 'Shelf count',
        ])->assertRedirect(route('inventory.stock-counts.index'));

        $this->assertDatabaseCount('stock_counts', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(7, $product->refresh()->stock_quantity);
    }

    public function test_negative_difference_and_no_difference_are_both_auditable(): void
    {
        $seller = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();
        app(AdjustStock::class)->execute($product, null, 10, StockMovementType::StockIn, $seller);
        $record = app(RecordStockCount::class);

        $decrease = $record->execute($product, null, 10, 4, $seller, (string) Str::uuid());
        $noChange = $record->execute($product, null, 4, 4, $seller, (string) Str::uuid());

        $this->assertSame(-6, $decrease->difference);
        $this->assertSame(0, $noChange->difference);
        $this->assertSame(4, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('stock_counts', 2);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => 'stock_count',
            'reference_id' => $decrease->id,
            'quantity' => -6,
        ]);
    }

    public function test_stale_count_or_reused_token_with_changed_details_cannot_change_stock(): void
    {
        $seller = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();
        $record = app(RecordStockCount::class);
        $token = (string) Str::uuid();
        $record->execute($product, null, 0, 5, $seller, $token);

        try {
            $record->execute($product, null, 0, 6, $seller, $token);
            $this->fail('Reusing a token with different details should fail.');
        } catch (InvalidStockCountException $exception) {
            $this->assertStringContainsString('already used', $exception->getMessage());
        }

        try {
            $record->execute($product, null, 0, 8, $seller, (string) Str::uuid());
            $this->fail('A stale observed quantity should fail.');
        } catch (InvalidStockCountException $exception) {
            $this->assertStringContainsString('Stock changed', $exception->getMessage());
        }

        $this->assertSame(5, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('stock_counts', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_variant_count_requires_the_correct_product_and_variant(): void
    {
        $seller = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $other = Product::factory()->create();
        $record = app(RecordStockCount::class);

        $this->expectException(InvalidStockCountException::class);
        $record->execute($other, $variant, 0, 2, $seller, (string) Str::uuid());
    }

    public function test_counting_a_product_with_variants_requires_a_variant_and_variant_count_updates_only_it(): void
    {
        $seller = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $record = app(RecordStockCount::class);

        try {
            $record->execute($product, null, 0, 2, $seller, (string) Str::uuid());
            $this->fail('A product with variants must be counted by variant.');
        } catch (InvalidStockCountException $exception) {
            $this->assertStringContainsString('Choose a variant', $exception->getMessage());
        }

        $count = $record->execute($product, $variant, 0, 2, $seller, (string) Str::uuid());

        $this->assertSame($variant->id, $count->product_variant_id);
        $this->assertSame(2, $variant->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('stock_counts', 1);
    }

    public function test_stocktake_page_shows_form_and_recent_count(): void
    {
        $seller = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create(['name' => 'Counted Shirt']);
        app(RecordStockCount::class)->execute($product, null, 0, 2, $seller, (string) Str::uuid());

        $this->actingAs($seller)->get(route('inventory.stock-counts.index'))
            ->assertOk()
            ->assertSeeText('Record a count')
            ->assertSeeText('Counted Shirt')
            ->assertSeeText('Count history');
    }

    public function test_stocktake_history_filters_by_item_difference_and_date(): void
    {
        $seller = $this->userWithPermissions(['inventory.adjust']);
        $shirt = Product::factory()->create(['name' => 'History Shirt', 'sku' => 'HIST-SHIRT']);
        $bag = Product::factory()->create(['name' => 'History Bag', 'sku' => 'HIST-BAG']);
        $record = app(RecordStockCount::class);

        $changed = $record->execute($shirt, null, 0, 3, $seller, (string) Str::uuid());
        $unchanged = $record->execute($shirt, null, 3, 3, $seller, (string) Str::uuid());
        $other = $record->execute($bag, null, 0, 2, $seller, (string) Str::uuid());
        $changed->forceFill(['created_at' => '2026-10-01 12:00:00'])->save();
        $unchanged->forceFill(['created_at' => '2026-10-03 12:00:00'])->save();
        $other->forceFill(['created_at' => '2026-10-05 12:00:00'])->save();

        $this->actingAs($seller)->get(route('inventory.stock-counts.index', [
            'product' => $shirt->uuid,
            'search' => 'HIST-SHIRT',
            'difference' => 'changed',
            'created_from' => '2026-10-01',
            'created_to' => '2026-10-01',
        ]))->assertOk()->assertViewHas('counts', function ($counts) use ($changed): bool {
            return $counts->total() === 1 && $counts->items()[0]->id === $changed->id;
        });

        $this->actingAs($seller)->get(route('inventory.stock-counts.index', [
            'difference' => 'unchanged',
        ]))->assertOk()->assertViewHas('counts', function ($counts) use ($unchanged): bool {
            return $counts->total() === 1 && $counts->items()[0]->id === $unchanged->id;
        });
    }

    public function test_stocktake_detail_shows_linked_movement_and_no_change_explanation(): void
    {
        $seller = $this->userWithPermissions(['inventory.adjust', 'inventory.view']);
        $product = Product::factory()->create(['name' => 'Audit Shirt']);
        $record = app(RecordStockCount::class);
        $changed = $record->execute($product, null, 0, 4, $seller, (string) Str::uuid(), 'Shelf check');
        $unchanged = $record->execute($product, null, 4, 4, $seller, (string) Str::uuid());

        $this->actingAs($seller)->get(route('inventory.stock-counts.show', $changed))
            ->assertOk()
            ->assertSeeText('Audit Shirt')
            ->assertSeeText('Shelf check')
            ->assertSeeText('An adjustment movement was recorded with this count.')
            ->assertSeeText('0 → 4');

        $this->actingAs($seller)->get(route('inventory.stock-counts.show', $unchanged))
            ->assertOk()
            ->assertSeeText('No adjustment was needed');
    }

    public function test_stocktake_history_rejects_invalid_filters_and_keeps_details_private(): void
    {
        $seller = $this->userWithPermissions(['inventory.adjust']);
        $viewer = $this->userWithPermissions(['inventory.view']);
        $product = Product::factory()->create();
        $count = app(RecordStockCount::class)->execute($product, null, 0, 1, $seller, (string) Str::uuid());

        $this->actingAs($seller)->get(route('inventory.stock-counts.index', [
            'difference' => 'invalid',
            'created_from' => '2026-10-10',
            'created_to' => '2026-10-01',
        ]))->assertRedirect(route('inventory.stock-counts.index'))->assertSessionHasErrors(['difference', 'created_to']);

        $this->actingAs($viewer)->get(route('inventory.stock-counts.show', $count))->assertForbidden();
    }

    public function test_stocktake_pages_and_submission_require_adjust_permission(): void
    {
        $viewer = $this->userWithPermissions(['inventory.view']);
        $product = Product::factory()->create();

        $this->actingAs($viewer)->get(route('inventory.stock-counts.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('inventory.stock-counts.store'), [
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'observed_quantity' => 0,
            'counted_quantity' => 5,
        ])->assertForbidden();
        $this->assertDatabaseCount('stock_counts', 0);
    }

    public function test_stocktake_form_validates_nonnegative_count_and_uuid(): void
    {
        $seller = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();

        $this->actingAs($seller)->post(route('inventory.stock-counts.store'), [
            'uuid' => 'not-a-uuid',
            'product_id' => $product->id,
            'observed_quantity' => 0,
            'counted_quantity' => -1,
        ])->assertSessionHasErrors(['uuid', 'counted_quantity']);
        $this->assertDatabaseCount('stock_counts', 0);
    }
}
