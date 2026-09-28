<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Product\Actions\CreateProduct;
use App\Domain\Product\Actions\UpdateProduct;
use App\Domain\Product\Models\Product;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $products = Product::query()
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('sku', 'like', $term));
            })
            ->withCount('variants')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('products.index', compact('products', 'search'));
    }

    public function create(): View
    {
        return view('products.create');
    }

    public function store(StoreProductRequest $request, CreateProduct $action): RedirectResponse
    {
        $product = $action->execute($request->validated(), $request->user());

        return redirect()->route('products.show', $product)->with('success', 'Product created successfully.');
    }

    public function show(Product $product): View
    {
        $product->load(['variants' => fn ($query) => $query->orderBy('color')->orderBy('size')]);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        return view('products.edit', compact('product'));
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProduct $action): RedirectResponse
    {
        $action->execute($product, $request->validated());

        return redirect()->route('products.show', $product)->with('success', 'Product updated successfully.');
    }
}
