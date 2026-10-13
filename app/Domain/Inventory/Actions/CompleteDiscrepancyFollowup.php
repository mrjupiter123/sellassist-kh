<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\DiscrepancyFollowupStatus;
use App\Domain\Inventory\Exceptions\InvalidStockCountException;
use App\Domain\Inventory\Models\StockDiscrepancyFollowup;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CompleteDiscrepancyFollowup
{
    public function execute(StockDiscrepancyFollowup $followup, User $actor, string $outcome): StockDiscrepancyFollowup
    {
        if (! $actor->active || ! ($actor->can('inventory.review') || ($actor->id === $followup->assigned_to && $actor->can('inventory.adjust')))) {
            throw new AuthorizationException;
        }

        $outcome = trim($outcome);

        if (mb_strlen($outcome) < 5 || mb_strlen($outcome) > 2000) {
            throw new InvalidStockCountException('Outcome note must be between 5 and 2000 characters.');
        }

        return DB::transaction(function () use ($followup, $actor, $outcome): StockDiscrepancyFollowup {
            $locked = StockDiscrepancyFollowup::query()->lockForUpdate()->findOrFail($followup->id);

            if ($locked->status === DiscrepancyFollowupStatus::Completed) {
                if ($locked->completed_by === $actor->id && $locked->outcome_note === $outcome) {
                    return $locked;
                }

                throw new InvalidStockCountException('This follow-up was already completed and cannot be changed.');
            }

            $locked->update([
                'status' => DiscrepancyFollowupStatus::Completed,
                'outcome_note' => $outcome,
                'completed_by' => $actor->id,
                'completed_at' => now(),
            ]);

            return $locked;
        });
    }
}
