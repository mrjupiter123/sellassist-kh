<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Http\Requests\Inventory\AdjustStockRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        $movements = StockMovement::query()
            ->with(['product:id,uuid,name', 'variant:id,uuid,color,size', 'creator:id,name'])
            ->latest()
            ->paginate(25);
        $products = Product::query()->with(['variants' => fn ($query) => $query->where('active', true)])->where('active', true)->orderBy('name')->get();
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

        return view('inventory.index', compact('movements', 'catalog'));
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
