<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Actions\ListStockMovements;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Http\Requests\Inventory\IndexStockMovementsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(IndexStockMovementsRequest $request, ListStockMovements $action): View
    {
        $filters = $request->validated();
        $products = Gate::allows('inventory.adjust')
            ? Product::query()->with(['variants' => fn ($query) => $query->where('active', true)])->where('active', true)->orderBy('name')->get()
            : collect();
        $catalog = $products->map(fn (Product $product): array => [
            'id' => $product->id,
            'name' => $product->name,
            'stock' => $product->stock_quantity,
            'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'name' => $variant->display_name,
                'stock' => $variant->stock_quantity,
            ])->values(),
        ])->values();
        $selectedProduct = isset($filters['product'])
            ? Product::query()->where('uuid', $filters['product'])->first(['id', 'uuid', 'name'])
            : null;
        $selectedVariant = isset($filters['variant'])
            ? ProductVariant::query()->where('uuid', $filters['variant'])->first(['id', 'uuid', 'color', 'size'])
            : null;

        return view('inventory.index', [
            'movements' => $action->execute($filters),
            'catalog' => $catalog,
            'filters' => $filters,
            'movementTypes' => StockMovementType::cases(),
            'selectedProduct' => $selectedProduct,
            'selectedVariant' => $selectedVariant,
        ]);
    }

    public function store(AdjustStockRequest $request, AdjustStock $action): RedirectResponse
    {
        $data = $request->validated();
        $product = Product::query()->findOrFail($data['product_id']);
        $variant = isset($data['product_variant_id'])
            ? ProductVariant::query()->findOrFail($data['product_variant_id'])
            : null;

        $action->execute(
            $product,
            $variant,
            (int) $data['quantity'],
            StockMovementType::from($data['type']),
            $request->user(),
            notes: $data['notes'] ?? null,
        );

        return back()->with('success', 'Stock adjusted successfully.');
    }
}
