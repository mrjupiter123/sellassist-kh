<?php

declare(strict_types=1);

namespace App\Domain\Product\Actions;

use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class CreateProductVariant
{
    public function __construct(private readonly AdjustStock $adjustStock) {}

    /** @param array<string, mixed> $data */
    public function execute(Product $product, array $data, ?User $createdBy = null): ProductVariant
    {
        return DB::transaction(function () use ($product, $data, $createdBy): ProductVariant {
            $initialStock = (int) ($data['initial_stock'] ?? 0);
            $variant = $product->variants()->create(Arr::except($data, 'initial_stock'));

            if ($initialStock > 0) {
                $this->adjustStock->execute(
                    $product,
                    $variant,
                    $initialStock,
                    StockMovementType::StockIn,
                    $createdBy,
                    notes: 'Initial variant stock',
                );
            }

            return $variant->refresh();
        });
    }
}
