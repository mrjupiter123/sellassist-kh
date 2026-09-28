<?php

declare(strict_types=1);

namespace App\Domain\Product\Actions;

use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Product\Models\Product;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class CreateProduct
{
    public function __construct(private readonly AdjustStock $adjustStock) {}

    /** @param array<string, mixed> $data */
    public function execute(array $data, ?User $createdBy = null): Product
    {
        return DB::transaction(function () use ($data, $createdBy): Product {
            $initialStock = (int) ($data['initial_stock'] ?? 0);
            $product = Product::query()->create(Arr::except($data, 'initial_stock'));

            if ($initialStock > 0) {
                $this->adjustStock->execute(
                    $product,
                    null,
                    $initialStock,
                    StockMovementType::StockIn,
                    $createdBy,
                    notes: 'Initial product stock',
                );
            }

            return $product->refresh();
        });
    }
}
