<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\ListStockDiscrepancies;
use App\Domain\Inventory\Actions\ReviewStockCountDiscrepancy;
use App\Domain\Inventory\Enums\DiscrepancyReviewFilter;
use App\Domain\Inventory\Enums\StockDiscrepancyCause;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Product\Models\Product;
use App\Http\Requests\Inventory\IndexStockDiscrepanciesRequest;
use App\Http\Requests\Inventory\ReviewStockCountDiscrepancyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockDiscrepancyController extends Controller
{
    public function index(IndexStockDiscrepanciesRequest $request, ListStockDiscrepancies $list): View
    {
        $filters = $request->validated();

        return view('inventory.discrepancies', [
            'counts' => $list->execute($filters),
            'summary' => $list->summary($filters),
            'filters' => $filters,
            'reviewFilters' => DiscrepancyReviewFilter::cases(),
            'selectedProduct' => isset($filters['product']) ? Product::query()->where('uuid', $filters['product'])->first() : null,
        ]);
    }

    public function storeReview(
        ReviewStockCountDiscrepancyRequest $request,
        StockCount $stockCount,
        ReviewStockCountDiscrepancy $action,
    ): RedirectResponse {
        $data = $request->validated();
        $action->execute($stockCount, $request->user(), $data['review_notes'], StockDiscrepancyCause::from($data['root_cause']));

        return redirect()->route('inventory.stock-counts.show', $stockCount)->with('success', 'Discrepancy review recorded.');
    }
}
