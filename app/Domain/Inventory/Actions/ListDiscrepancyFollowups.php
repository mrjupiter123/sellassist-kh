<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\StockDiscrepancyFollowup;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListDiscrepancyFollowups
{
    public function execute(User $viewer, ?string $status): LengthAwarePaginator
    {
        $query = StockDiscrepancyFollowup::query()
            ->with(['assignee:id,name', 'review.stockCount.product:id,uuid,name', 'review.stockCount.variant:id,uuid,color,size'])
            ->orderBy('completed_at')
            ->orderBy('due_on')
            ->orderByDesc('id');

        if (! $viewer->can('inventory.review')) {
            $query->where('assigned_to', $viewer->id);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate(25)->withQueryString();
    }
}
