<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\StockCountTask;
use App\Models\User;
use App\Notifications\StockCountTaskOverdue;
use Illuminate\Support\Facades\DB;

class RemindOverdueStockCountTasks
{
    public function execute(?string $today = null): int
    {
        $today ??= now('Asia/Phnom_Penh')->toDateString();
        $sent = 0;

        StockCountTask::query()
            ->whereNull('stock_count_id')
            ->whereNotNull('due_on')
            ->where('due_on', '<', $today)
            ->chunkById(100, function ($tasks) use ($today, &$sent): void {
                foreach ($tasks as $task) {
                    if ($this->remindTask($task, $today)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function remindTask(StockCountTask $task, string $today): bool
    {
        return DB::transaction(function () use ($task, $today): bool {
            $locked = StockCountTask::query()->lockForUpdate()->findOrFail($task->id);

            if ($locked->stock_count_id !== null || $locked->due_on === null || $locked->due_on->toDateString() >= $today) {
                return false;
            }

            $assignee = User::query()->lockForUpdate()->findOrFail($locked->assigned_to);

            if (! $assignee->active || ! $assignee->can('inventory.adjust')) {
                return false;
            }

            $time = now();
            $inserted = DB::table('stock_count_task_reminders')->insertOrIgnore([
                'stock_count_task_id' => $locked->id,
                'user_id' => $assignee->id,
                'notified_at' => $time,
                'created_at' => $time,
                'updated_at' => $time,
            ]);

            if ($inserted === 0) {
                return false;
            }

            $assignee->notify(new StockCountTaskOverdue($locked));

            return true;
        });
    }
}
