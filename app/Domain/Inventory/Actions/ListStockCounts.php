<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\StockCountDifferenceFilter;
use App\Domain\Inventory\Models\StockCount;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListStockCounts
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
     * @return Builder<StockCount>
     */
    public function query(array $filters): Builder
    {
        $query = StockCount::query()
            ->with(['product:id,uuid,name,sku', 'variant:id,uuid,color,size,sku', 'creator:id,name']);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($query) => $query
                ->whereHas('product', fn ($product) => $product
                    ->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term))
                ->orWhereHas('variant', fn ($variant) => $variant->where('sku', 'like', $term)));
        }

        if (! empty($filters['product'])) {
            $query->whereHas('product', fn ($product) => $product->where('uuid', $filters['product']));
        }

        if (! empty($filters['variant'])) {
            $query->whereHas('variant', fn ($variant) => $variant->where('uuid', $filters['variant']));
        }

        if (! empty($filters['difference'])) {
            $query->where('difference', $filters['difference'] === StockCountDifferenceFilter::Changed->value ? '!=' : '=', 0);
        }

        if (! empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from'].' 00:00:00');
        }

        if (! empty($filters['created_to'])) {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['created_to'])->addDay()->format('Y-m-d 00:00:00'));
        }

        return $query;
    }
}
