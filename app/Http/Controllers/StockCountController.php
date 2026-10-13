<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\ListStockCounts;
use App\Domain\Inventory\Actions\RecordStockCount;
use App\Domain\Inventory\Enums\StockCountDifferenceFilter;
use App\Domain\Inventory\Enums\StockDiscrepancyCause;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Http\Requests\Inventory\IndexStockCountsRequest;
use App\Http\Requests\Inventory\StoreStockCountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StockCountController extends Controller
{
    public function index(IndexStockCountsRequest $request, ListStockCounts $listStockCounts): View
    {
        $filters = $request->validated();
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

        return view('inventory.stock-counts', [
            'catalog' => $catalog,
            'counts' => $listStockCounts->execute($filters),
            'filters' => $filters,
            'differenceFilters' => StockCountDifferenceFilter::cases(),
            'selectedProduct' => isset($filters['product']) ? Product::query()->where('uuid', $filters['product'])->first() : null,
            'selectedVariant' => isset($filters['variant']) ? ProductVariant::query()->where('uuid', $filters['variant'])->first() : null,
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function show(StockCount $stockCount): View
    {
        return view('inventory.stock-count-show', [
            'count' => $stockCount->load(['product:id,uuid,name,sku', 'variant:id,uuid,color,size,sku', 'creator:id,name', 'movement', 'discrepancyReview.reviewer:id,name', 'discrepancyReview.followup']),
            'discrepancyCauses' => StockDiscrepancyCause::cases(),
            'assignees' => auth()->user()->can('inventory.review') && $stockCount->discrepancyReview && ! $stockCount->discrepancyReview->followup
                ? User::permission('inventory.adjust')->where('active', true)->orderBy('name')->get(['id', 'uuid', 'name'])
                : collect(),
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
