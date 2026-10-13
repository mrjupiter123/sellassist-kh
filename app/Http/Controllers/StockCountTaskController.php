<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\CompleteStockCountTask;
use App\Domain\Inventory\Actions\CreateStockCountTask;
use App\Domain\Inventory\Models\StockCountTask;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Http\Requests\Inventory\CompleteStockCountTaskRequest;
use App\Http\Requests\Inventory\StoreStockCountTaskRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StockCountTaskController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->where('active', true)
            ->where(fn ($query) => $query->doesntHave('variants')->orWhereHas('variants', fn ($variants) => $variants->where('active', true)))
            ->with(['variants' => fn ($query) => $query->where('active', true)])
            ->orderBy('name')
            ->get();

        return view('inventory.stock-count-tasks', [
            'catalog' => $products->map(fn (Product $product): array => [
                'uuid' => $product->uuid,
                'name' => $product->name,
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                    'uuid' => $variant->uuid,
                    'name' => $variant->display_name,
                ])->values(),
            ])->values(),
            'assignees' => User::query()->where('active', true)->permission('inventory.adjust')->orderBy('name')->get(['id', 'uuid', 'name']),
            'tasks' => StockCountTask::query()
                ->with(['product:id,uuid,name', 'variant:id,uuid,color,size', 'assignee:id,name', 'stockCount:id,uuid'])
                ->orderBy('completed_at')
                ->orderBy('due_on')
                ->orderByDesc('id')
                ->paginate(20),
            'openCount' => StockCountTask::query()->whereNull('stock_count_id')->count(),
            'completedCount' => StockCountTask::query()->whereNotNull('stock_count_id')->count(),
        ]);
    }

    public function store(StoreStockCountTaskRequest $request, CreateStockCountTask $action): RedirectResponse
    {
        $data = $request->validated();
        $task = $action->execute(
            Product::query()->where('uuid', $data['product'])->firstOrFail(),
            isset($data['variant']) ? ProductVariant::query()->where('uuid', $data['variant'])->firstOrFail() : null,
            User::query()->where('uuid', $data['assignee'])->firstOrFail(),
            $request->user(),
            $data['due_on'] ?? null,
            $data['instructions'] ?? null,
        );

        return redirect()->route('inventory.stock-count-tasks.show', $task)->with('success', 'Count assigned.');
    }

    public function show(StockCountTask $stockCountTask): View
    {
        return view('inventory.stock-count-task-show', [
            'task' => $stockCountTask->load(['product', 'variant', 'assignee:id,name', 'creator:id,name', 'stockCount:id,uuid,difference', 'campaign:id,uuid,name']),
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function complete(
        CompleteStockCountTaskRequest $request,
        StockCountTask $stockCountTask,
        CompleteStockCountTask $action,
    ): RedirectResponse {
        $data = $request->validated();
        $action->execute(
            $stockCountTask,
            $request->user(),
            (int) $data['observed_quantity'],
            (int) $data['counted_quantity'],
            $data['uuid'],
            $data['notes'] ?? null,
        );

        return redirect()->route('inventory.stock-count-tasks.show', $stockCountTask)->with('success', 'Assigned count completed.');
    }
}
