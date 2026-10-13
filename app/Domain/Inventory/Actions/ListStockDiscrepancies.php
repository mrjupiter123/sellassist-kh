<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\DiscrepancyReviewFilter;
use App\Domain\Inventory\Models\StockCount;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListStockDiscrepancies
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
     * @return array{total: int, reviewed: int, pending: int}
     */
    public function summary(array $filters): array
    {
        unset($filters['reviewed']);
        $query = $this->query($filters);
        $total = (clone $query)->count();
        $reviewed = (clone $query)->whereHas('discrepancyReview')->count();

        return ['total' => $total, 'reviewed' => $reviewed, 'pending' => $total - $reviewed];
    }

    /** @param array<string, mixed> $filters
     * @return Builder<StockCount>
     */
    public function query(array $filters): Builder
    {
        $query = StockCount::query()
            ->where('difference', '!=', 0)
            ->with(['product:id,uuid,name,sku', 'variant:id,uuid,color,size,sku', 'creator:id,name', 'discrepancyReview.reviewer:id,name']);

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

        if (($filters['reviewed'] ?? null) === DiscrepancyReviewFilter::Pending->value) {
            $query->whereDoesntHave('discrepancyReview');
        } elseif (($filters['reviewed'] ?? null) === DiscrepancyReviewFilter::Reviewed->value) {
            $query->whereHas('discrepancyReview');
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
