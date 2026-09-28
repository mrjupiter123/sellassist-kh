<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Product\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjustment_updates_stock_and_records_before_and_after_quantities(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $action = app(AdjustStock::class);

        $action->execute($product, null, 10, StockMovementType::StockIn, $user);
        $movement = $action->execute($product, null, -3, StockMovementType::Adjustment, $user);

        $this->assertSame(7, $product->refresh()->stock_quantity);
        $this->assertSame(10, $movement->quantity_before);
        $this->assertSame(7, $movement->quantity_after);
    }

    public function test_stock_can_never_become_negative(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();

        $this->expectException(InsufficientStockException::class);

        app(AdjustStock::class)->execute($product, null, 1, StockMovementType::StockOut, $user);
    }
}
