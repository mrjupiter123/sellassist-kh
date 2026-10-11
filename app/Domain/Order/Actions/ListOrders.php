<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Order\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListOrders
{
    /** @param array<string, mixed> $filters */
    public function execute(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($filters);
    }

    /** @param array<string, mixed> $filters
     * @return Builder<Order>
     */
    public function query(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $query = Order::query()->with('customer:id,uuid,name,phone');

        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';

            $query->where(fn ($query) => $query
                ->where('order_number', 'like', $term)
                ->orWhere('customer_name', 'like', $term)
                ->orWhere('customer_phone', 'like', $term)
                ->orWhereHas('customer', fn ($customer) => $customer
                    ->where('name', 'like', $term)
                    ->orWhere('phone', 'like', $term)));
        }

        foreach (['status', 'payment_status', 'source', 'currency'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        if (! empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from'].' 00:00:00');
        }

        if (! empty($filters['created_to'])) {
            $exclusiveEnd = CarbonImmutable::parse($filters['created_to'])->addDay()->format('Y-m-d 00:00:00');
            $query->where('created_at', '<', $exclusiveEnd);
        }

        return $query;
    }
}
