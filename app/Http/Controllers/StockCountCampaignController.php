<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\CreateStockCountCampaign;
use App\Domain\Inventory\Actions\GetStockCountCampaignProgress;
use App\Domain\Inventory\Models\StockCountCampaign;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Http\Requests\Inventory\StoreStockCountCampaignRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockCountCampaignController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->where('active', true)
            ->where(fn ($query) => $query->doesntHave('variants')->orWhereHas('variants', fn ($variants) => $variants->where('active', true)))
            ->with(['variants' => fn ($query) => $query->where('active', true)])
            ->orderBy('name')
            ->get();

        return view('inventory.stock-count-campaigns', [
            'catalog' => $products->map(fn (Product $product): array => [
                'uuid' => $product->uuid,
                'name' => $product->name,
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                    'uuid' => $variant->uuid,
                    'name' => $variant->display_name,
                ])->values(),
            ])->values(),
            'assignees' => User::query()->where('active', true)->permission('inventory.adjust')->orderBy('name')->get(['id', 'uuid', 'name']),
            'campaigns' => StockCountCampaign::query()
                ->with('creator:id,name')
                ->withCount(['tasks', 'tasks as completed_tasks_count' => fn ($query) => $query->whereNotNull('stock_count_id')])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function store(StoreStockCountCampaignRequest $request, CreateStockCountCampaign $action): RedirectResponse
    {
        $data = $request->validated();
        $campaign = $action->execute($data['name'], $data['due_on'] ?? null, $data['assignments'], $request->user());

        return redirect()->route('inventory.stock-count-campaigns.show', $campaign)->with('success', 'Count campaign created.');
    }

    public function show(StockCountCampaign $stockCountCampaign, GetStockCountCampaignProgress $progress): View
    {
        return view('inventory.stock-count-campaign-show', [
            'campaign' => $stockCountCampaign->load('creator:id,name'),
            'progress' => $progress->execute($stockCountCampaign),
        ]);
    }
}
