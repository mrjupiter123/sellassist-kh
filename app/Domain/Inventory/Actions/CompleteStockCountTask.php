<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Exceptions\InvalidStockCountException;
use App\Domain\Inventory\Models\StockCountTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompleteStockCountTask
{
    public function __construct(private readonly RecordStockCount $recordStockCount) {}

    public function execute(
        StockCountTask $task,
        User $actor,
        int $observedQuantity,
        int $countedQuantity,
        string $submissionUuid,
        ?string $notes = null,
    ): StockCountTask {
        if (! $actor->active || ! $actor->can('inventory.adjust')
            || ($task->assigned_to !== $actor->id && ! $actor->hasRole('admin'))) {
            throw new InvalidStockCountException('Only the assigned user or an administrator can complete this count.');
        }

        return DB::transaction(function () use ($task, $actor, $observedQuantity, $countedQuantity, $submissionUuid, $notes): StockCountTask {
            $locked = StockCountTask::query()->lockForUpdate()->findOrFail($task->id);
            $locked->load(['product', 'variant']);

            if ($locked->stock_count_id !== null) {
                if ($locked->stockCount->uuid !== $submissionUuid) {
                    throw new InvalidStockCountException('This planned count has already been completed.');
                }

                $this->recordStockCount->execute(
                    $locked->product,
                    $locked->variant,
                    $observedQuantity,
                    $countedQuantity,
                    $actor,
                    $submissionUuid,
                    $notes,
                );

                return $locked;
            }

            $count = $this->recordStockCount->execute(
                $locked->product,
                $locked->variant,
                $observedQuantity,
                $countedQuantity,
                $actor,
                $submissionUuid,
                $notes,
            );

            if (StockCountTask::query()->where('stock_count_id', $count->id)->exists()) {
                throw new InvalidStockCountException('This physical count is already linked to another assignment.');
            }

            $locked->update([
                'stock_count_id' => $count->id,
                'completed_at' => now(),
            ]);

            return $locked;
        });
    }
}
