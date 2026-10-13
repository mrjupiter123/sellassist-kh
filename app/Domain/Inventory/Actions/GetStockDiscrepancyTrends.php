<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\DiscrepancyTrendWindow;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Inventory\Models\StockCountDiscrepancyReview;
use Carbon\CarbonImmutable;

class GetStockDiscrepancyTrends
{
    /** @return array<string, mixed> */
    public function execute(DiscrepancyTrendWindow $window): array
    {
        $from = CarbonImmutable::now()->startOfDay()->subDays($window->days() - 1);
        $base = StockCount::query()->where('difference', '!=', 0)->where('created_at', '>=', $from);
        $total = (clone $base)->count();
        $reviewed = (clone $base)->whereHas('discrepancyReview')->count();

        $daily = (clone $base)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as discrepancy_count, SUM(ABS(difference)) as absolute_units')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->get();

        $topProducts = (clone $base)
            ->select('product_id')
            ->selectRaw('COUNT(*) as discrepancy_count, SUM(ABS(difference)) as absolute_units')
            ->groupBy('product_id')
            ->orderByDesc('discrepancy_count')
            ->limit(10)
            ->with('product:id,uuid,name')
            ->get();

        $causes = StockCountDiscrepancyReview::query()
            ->whereHas('stockCount', fn ($query) => $query->where('difference', '!=', 0)->where('created_at', '>=', $from))
            ->select('root_cause')
            ->selectRaw('COUNT(*) as review_count')
            ->groupBy('root_cause')
            ->orderByDesc('review_count')
            ->get();

        return [
            'window' => $window,
            'from' => $from,
            'total' => $total,
            'reviewed' => $reviewed,
            'pending' => $total - $reviewed,
            'daily' => $daily,
            'topProducts' => $topProducts,
            'causes' => $causes,
        ];
    }
}
