<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\StockDiscrepancyCause;
use App\Domain\Inventory\Exceptions\InvalidStockCountException;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Inventory\Models\StockCountDiscrepancyReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewStockCountDiscrepancy
{
    public function execute(StockCount $count, User $reviewer, string $notes, StockDiscrepancyCause $cause): StockCountDiscrepancyReview
    {
        $notes = trim($notes);

        if (! $reviewer->active || ! $reviewer->can('inventory.review')) {
            throw new InvalidStockCountException('You are not authorized to review stock discrepancies.');
        }

        if (mb_strlen($notes) < 5 || mb_strlen($notes) > 2000) {
            throw new InvalidStockCountException('Review notes must be between 5 and 2000 characters.');
        }

        return DB::transaction(function () use ($count, $reviewer, $notes, $cause): StockCountDiscrepancyReview {
            $locked = StockCount::query()->lockForUpdate()->findOrFail($count->id);

            if ($locked->difference === 0) {
                throw new InvalidStockCountException('A count without a difference does not need discrepancy review.');
            }

            if (! $locked->movement()->exists()) {
                throw new InvalidStockCountException('The linked stock adjustment is missing; investigate before reviewing this discrepancy.');
            }

            $existing = $locked->discrepancyReview()->first();

            if ($existing !== null) {
                if ($existing->reviewed_by !== $reviewer->id || $existing->review_notes !== $notes || $existing->root_cause !== $cause) {
                    throw new InvalidStockCountException('This discrepancy was already reviewed and cannot be changed.');
                }

                return $existing;
            }

            return $locked->discrepancyReview()->create([
                'reviewed_by' => $reviewer->id,
                'review_notes' => $notes,
                'root_cause' => $cause,
                'reviewed_at' => now(),
            ]);
        });
    }
}
