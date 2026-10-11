<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\RecordStockCount;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Http\Requests\Inventory\StoreStockCountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StockCountController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->where('active', true)
            ->where(fn ($query) => $query->doesntHave('variants')->orWhereHas('variants', fn ($variants) => $variants->where('active', true)))
            ->with(['variants' => fn ($query) => $query->where('active', true)])
            ->orderBy('name')
            ->get();

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

        $counts = StockCount::query()
            ->with(['product:id,uuid,name', 'variant:id,uuid,color,size', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('inventory.stock-counts', [
            'catalog' => $catalog,
            'counts' => $counts,
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function store(StoreStockCountRequest $request, RecordStockCount $action): RedirectResponse
    {
        $data = $request->validated();
        $product = Product::query()->findOrFail($data['product_id']);
        $variant = isset($data['product_variant_id'])
            ? ProductVariant::query()->findOrFail($data['product_variant_id'])
            : null;

        $count = $action->execute(
            $product,
            $variant,
            (int) $data['observed_quantity'],
            (int) $data['counted_quantity'],
            $request->user(),
            $data['uuid'],
            $data['notes'] ?? null,
        );

        return redirect()->route('inventory.stock-counts.index')->with(
            'success',
            $count->difference === 0 ? 'Physical count recorded; stock was unchanged.' : 'Physical count recorded and stock reconciled.',
        );
    }
}
