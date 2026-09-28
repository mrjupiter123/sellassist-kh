<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AdjustStock
{
    public function execute(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        StockMovementType $type,
        ?User $createdBy = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
    ): StockMovement {
        if ($quantity === 0) {
            throw new DomainException('A stock adjustment cannot be zero.');
        }

        $quantity = match ($type) {
            StockMovementType::StockIn, StockMovementType::Return => abs($quantity),
            StockMovementType::StockOut, StockMovementType::Order => -abs($quantity),
            StockMovementType::Adjustment => $quantity,
        };

        return DB::transaction(function () use ($product, $variant, $quantity, $type, $createdBy, $referenceType, $referenceId, $notes): StockMovement {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $stockable = $lockedProduct;

            if ($variant !== null) {
                $stockable = ProductVariant::query()->lockForUpdate()->findOrFail($variant->getKey());

                if ($stockable->product_id !== $lockedProduct->id) {
                    throw new DomainException('The selected variant does not belong to this product.');
                }
            }

            $before = (int) $stockable->stock_quantity;
            $after = $before + $quantity;

            if ($after < 0) {
                $name = $variant === null ? $lockedProduct->name : "{$lockedProduct->name} ({$stockable->display_name})";

                throw InsufficientStockException::forItem($name, $before, abs($quantity));
            }

            $stockable->stock_quantity = $after;
            $stockable->save();

            return StockMovement::query()->create([
                'product_id' => $lockedProduct->id,
                'product_variant_id' => $variant?->id,
                'type' => $type,
                'quantity' => $quantity,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'created_by' => $createdBy?->id,
            ]);
        });
    }
}
