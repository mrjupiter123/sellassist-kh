<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Inventory\Services\LowStockItems;
use Illuminate\View\View;

class LowStockController extends Controller
{
    public function __invoke(LowStockItems $items): View
    {
        return view('inventory.low-stock', [
            'products' => $items->products()
                ->orderBy('stock_quantity')
                ->orderBy('id')
                ->paginate(20, ['*'], 'products_page')
                ->withQueryString(),
            'variants' => $items->variants()
                ->orderBy('stock_quantity')
                ->orderBy('id')
                ->paginate(20, ['*'], 'variants_page')
                ->withQueryString(),
        ]);
    }
}
