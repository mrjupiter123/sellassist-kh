<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\GetStockDiscrepancyTrends;
use App\Domain\Inventory\Enums\DiscrepancyTrendWindow;
use App\Http\Requests\Inventory\IndexStockDiscrepancyTrendsRequest;
use Illuminate\View\View;

class StockDiscrepancyTrendController extends Controller
{
    public function __invoke(IndexStockDiscrepancyTrendsRequest $request, GetStockDiscrepancyTrends $trends): View
    {
        $window = DiscrepancyTrendWindow::from($request->validated('window', DiscrepancyTrendWindow::ThirtyDays->value));

        return view('inventory.discrepancy-trends', [
            'report' => $trends->execute($window),
            'windows' => DiscrepancyTrendWindow::cases(),
        ]);
    }
}
