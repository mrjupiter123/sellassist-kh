<?php

declare(strict_types=1);

namespace App\Domain\Product\Actions;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class UpdateProductVariant
{
    /** @param array<string, mixed> $data */
    public function execute(Product $product, ProductVariant $variant, array $data): ProductVariant
    {
        if ($variant->product_id !== $product->getKey()) {
            throw (new ModelNotFoundException)->setModel(ProductVariant::class, [$variant->getKey()]);
        }

        $variant->update($data);

        return $variant->refresh();
    }
}
