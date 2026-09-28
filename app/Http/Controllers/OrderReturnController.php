<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Order\Models\Order;
use App\Domain\OrderReturn\Actions\CreateOrderReturn;
use App\Http\Requests\OrderReturn\StoreOrderReturnRequest;
use Illuminate\Http\RedirectResponse;

class OrderReturnController extends Controller
{
    public function store(
        StoreOrderReturnRequest $request,
        Order $order,
        CreateOrderReturn $action,
    ): RedirectResponse {
        $orderReturn = $action->execute($order, $request->validated(), $request->user());

        return back()->with('success', "Return {$orderReturn->return_number} recorded successfully.");
    }
}
