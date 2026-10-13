<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Inventory\Actions\RemindOverdueStockCountTasks as ReminderAction;
use Illuminate\Console\Command;

class RemindOverdueStockCountTasks extends Command
{
    protected $signature = 'inventory:remind-overdue-counts';

    protected $description = 'Notify assigned staff once for each overdue physical count';

    public function handle(ReminderAction $action): int
    {
        $this->info($action->execute().' overdue count reminder(s) sent.');

        return self::SUCCESS;
    }
}
