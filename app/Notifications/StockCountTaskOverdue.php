<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Inventory\Models\StockCountTask;
use Illuminate\Notifications\Notification;

class StockCountTaskOverdue extends Notification
{
    public function __construct(private readonly StockCountTask $task) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'stock_count_task_overdue',
            'title' => 'Physical count is overdue',
            'message' => 'A physical count assigned to you is past its due date. Review the assignment and record a fresh count when ready.',
            'url' => route('inventory.stock-count-tasks.show', $this->task, false),
        ];
    }
}
