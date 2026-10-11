<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Exceptions\InvalidStockCountException;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordStockCount
{
    public function __construct(private readonly AdjustStock $adjustStock) {}

    public function execute(
        Product $product,
        ?ProductVariant $variant,
        int $observedQuantity,
        int $countedQuantity,
        User $actor,
        string $uuid,
        ?string $notes = null,
    ): StockCount {
        if ($observedQuantity < 0 || $countedQuantity < 0 || $countedQuantity > 100000000) {
            throw new InvalidStockCountException('Counted and observed quantities must be valid non-negative numbers.');
        }

        $existing = StockCount::query()->where('uuid', $uuid)->first();

        if ($existing !== null) {
            return $this->matchingExisting($existing, $product, $variant, $observedQuantity, $countedQuantity, $actor, $notes);
        }

        return DB::transaction(function () use ($product, $variant, $observedQuantity, $countedQuantity, $actor, $uuid, $notes): StockCount {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $lockedVariant = null;

            if ($variant !== null) {
                $lockedVariant = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);

                if ($lockedVariant->product_id !== $lockedProduct->id) {
                    throw new InvalidStockCountException('The selected variant does not belong to this product.');
                }
            } elseif ($lockedProduct->variants()->exists()) {
                throw new InvalidStockCountException('Choose a variant when counting a product with variants.');
            }

            // A retry waiting on the same product lock sees the completed first submission.
            $existing = StockCount::query()->where('uuid', $uuid)->lockForUpdate()->first();

            if ($existing !== null) {
                return $this->matchingExisting($existing, $product, $variant, $observedQuantity, $countedQuantity, $actor, $notes);
            }

            $currentQuantity = (int) ($lockedVariant?->stock_quantity ?? $lockedProduct->stock_quantity);

            if ($observedQuantity !== $currentQuantity) {
                throw new InvalidStockCountException('Stock changed since this count form was opened. Refresh and recount before saving.');
            }

            $difference = $countedQuantity - $currentQuantity;

            if ($difference < -2147483648 || $difference > 2147483647) {
                throw new InvalidStockCountException('The stock difference is too large to record.');
            }

            $count = StockCount::query()->create([
                'uuid' => $uuid,
                'product_id' => $lockedProduct->id,
                'product_variant_id' => $lockedVariant?->id,
                'expected_quantity' => $currentQuantity,
                'counted_quantity' => $countedQuantity,
                'difference' => $difference,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            if ($difference !== 0) {
                $this->adjustStock->execute(
                    $lockedProduct,
                    $lockedVariant,
                    $difference,
                    StockMovementType::Adjustment,
                    $actor,
                    referenceType: 'stock_count',
                    referenceId: $count->id,
                    notes: 'Physical stock count',
                );
            }

            return $count;
        });
    }

    private function matchingExisting(
        StockCount $existing,
        Product $product,
        ?ProductVariant $variant,
        int $observedQuantity,
        int $countedQuantity,
        User $actor,
        ?string $notes,
    ): StockCount {
        if ($existing->product_id !== $product->id
            || $existing->product_variant_id !== $variant?->id
            || $existing->expected_quantity !== $observedQuantity
            || $existing->counted_quantity !== $countedQuantity
            || $existing->created_by !== $actor->id
            || $existing->notes !== $notes) {
            throw new InvalidStockCountException('This count submission was already used for different details. Refresh the form and try again.');
        }

        return $existing;
    }
}
