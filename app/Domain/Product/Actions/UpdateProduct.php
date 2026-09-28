<?php

declare(strict_types=1);

namespace App\Domain\Product\Actions;

use App\Domain\Product\Models\Product;

final class UpdateProduct
{
    /** @param array<string, mixed> $data */
    public function execute(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->refresh();
    }
}
