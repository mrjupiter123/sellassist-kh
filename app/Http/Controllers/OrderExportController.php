<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Order\Actions\ExportOrders;
use App\Http\Requests\Order\IndexOrdersRequest;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderExportController extends Controller
{
    public function __invoke(IndexOrdersRequest $request, ExportOrders $action): StreamedResponse|RedirectResponse
    {
        $filters = $request->validated();

        if (! $action->isWithinLimit($filters)) {
            return redirect()->route('orders.index', $filters)
                ->withErrors(['export' => 'The export contains more than 5,000 orders. Narrow the date range or other filters and try again.']);
        }

        return response()->streamDownload(
            fn () => $action->write($filters),
            'orders-'.now()->format('Ymd-His').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store'],
        );
    }
}
