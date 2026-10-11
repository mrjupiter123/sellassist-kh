<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListStockMovements
{
    /** @param array<string, mixed> $filters */
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = StockMovement::query()
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

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from'].' 00:00:00');
        }

        if (! empty($filters['created_to'])) {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['created_to'])->addDay()->format('Y-m-d 00:00:00'));
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->appends($filters);
    }
}
