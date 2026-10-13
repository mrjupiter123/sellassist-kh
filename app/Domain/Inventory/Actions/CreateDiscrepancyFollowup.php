<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\DiscrepancyFollowupStatus;
use App\Domain\Inventory\Exceptions\InvalidStockCountException;
use App\Domain\Inventory\Models\StockCountDiscrepancyReview;
use App\Domain\Inventory\Models\StockDiscrepancyFollowup;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CreateDiscrepancyFollowup
{
    public function execute(StockCountDiscrepancyReview $review, User $assignee, User $creator, string $title, ?string $dueOn): StockDiscrepancyFollowup
    {
        if (! $creator->active || ! $creator->can('inventory.review')) {
            throw new AuthorizationException;
        }

        if (! $assignee->active || ! $assignee->can('inventory.adjust')) {
            throw new InvalidStockCountException('Assign this follow-up to an active inventory operator.');
        }

        $title = trim($title);

        if ($title === '' || mb_strlen($title) > 150) {
            throw new InvalidStockCountException('Follow-up title must be between 1 and 150 characters.');
        }

        return DB::transaction(function () use ($review, $assignee, $creator, $title, $dueOn): StockDiscrepancyFollowup {
            $locked = StockCountDiscrepancyReview::query()->lockForUpdate()->findOrFail($review->id);
            $existing = $locked->followup()->first();

            if ($existing !== null) {
                if ($existing->title === $title && $existing->assigned_to === $assignee->id && $existing->due_on?->format('Y-m-d') === $dueOn && $existing->created_by === $creator->id) {
                    return $existing;
                }

                throw new InvalidStockCountException('This discrepancy already has a follow-up and cannot be reassigned.');
            }

            return $locked->followup()->create([
                'title' => $title,
                'assigned_to' => $assignee->id,
                'due_on' => $dueOn,
                'status' => DiscrepancyFollowupStatus::Open,
                'created_by' => $creator->id,
            ]);
        });
    }
}
