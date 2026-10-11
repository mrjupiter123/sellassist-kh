<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

class LowStockItems
{
    /** @return Builder<Product> */
    public function products(): Builder
    {
        return Product::query()
            ->where('active', true)
            ->doesntHave('variants')
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    /** @return Builder<ProductVariant> */
    public function variants(): Builder
    {
        return ProductVariant::query()
            ->with('product:id,uuid,name')
            ->where('active', true)
            ->whereHas('product', fn (Builder $query) => $query->where('active', true))
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }
}
