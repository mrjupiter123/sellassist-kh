<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Product\Actions\CreateProductVariant;
use App\Domain\Product\Actions\UpdateProductVariant;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Http\Requests\Product\StoreProductVariantRequest;
use App\Http\Requests\Product\UpdateProductVariantRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

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

    public function edit(Product $product, ProductVariant $variant): View
    {
        abort_unless($variant->product_id === $product->getKey(), 404);

        return view('products.variants.edit', compact('product', 'variant'));
    }

    public function update(
        UpdateProductVariantRequest $request,
        Product $product,
        ProductVariant $variant,
        UpdateProductVariant $action,
    ): RedirectResponse {
        $action->execute($product, $variant, $request->validated());

        return redirect()->route('products.show', $product)->with('success', 'Variant updated successfully.');
    }
}
