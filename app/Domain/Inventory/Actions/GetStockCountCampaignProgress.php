<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\StockCountCampaign;
use App\Domain\Inventory\Models\StockCountTask;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class GetStockCountCampaignProgress
{
    /** @return array{tasks: EloquentCollection<int, StockCountTask>, total: int, completed: int, open: int, discrepancies: int, byAssignee: Collection<int, array{name: string, total: int, completed: int}>} */
    public function execute(StockCountCampaign $campaign): array
    {
        $tasks = $campaign->tasks()
            ->with(['product:id,uuid,name', 'variant:id,uuid,color,size', 'assignee:id,name', 'stockCount:id,uuid,difference'])
            ->orderBy('completed_at')
            ->orderBy('due_on')
            ->orderBy('id')
            ->get();

        $completed = $tasks->whereNotNull('stock_count_id')->count();

        return [
            'tasks' => $tasks,
            'total' => $tasks->count(),
            'completed' => $completed,
            'open' => $tasks->count() - $completed,
            'discrepancies' => $tasks->filter(fn (StockCountTask $task): bool => $task->stockCount?->difference !== null && $task->stockCount->difference !== 0)->count(),
            'byAssignee' => $tasks->groupBy('assigned_to')->map(fn ($assigned) => [
                'name' => $assigned->first()->assignee->name,
                'total' => $assigned->count(),
                'completed' => $assigned->whereNotNull('stock_count_id')->count(),
            ])->values(),
        ];
    }
}
