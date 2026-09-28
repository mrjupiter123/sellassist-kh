<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Product\Actions\CreateProductVariant;
use App\Domain\Product\Models\Product;
use App\Http\Requests\Product\StoreProductVariantRequest;
use Illuminate\Http\RedirectResponse;

class ProductVariantController extends Controller
{
    public function store(
        StoreProductVariantRequest $request,
        Product $product,
        CreateProductVariant $action,
    ): RedirectResponse {
        $action->execute($product, $request->validated(), $request->user());

        return redirect()->route('products.show', $product)->with('success', 'Variant created successfully.');
    }
}
