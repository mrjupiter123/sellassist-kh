<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\ExportStockMovements;
use App\Http\Requests\Inventory\IndexStockMovementsRequest;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockMovementExportController extends Controller
{
    public function __invoke(IndexStockMovementsRequest $request, ExportStockMovements $action): StreamedResponse|RedirectResponse
    {
        $filters = $request->validated();

        if (! $action->isWithinLimit($filters)) {
            return redirect()->route('inventory.index', $filters)
                ->withErrors(['export' => 'The export contains more than 5,000 stock movements. Narrow the date range or other filters and try again.']);
        }

        return response()->streamDownload(
            fn () => $action->write($filters),
            'stock-movements-'.now()->format('Ymd-His').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store'],
        );
    }
}
