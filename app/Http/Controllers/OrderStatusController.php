<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Http\Requests\Order\ChangeOrderStatusRequest;
use Illuminate\Http\RedirectResponse;

class OrderStatusController extends Controller
{
    public function update(
        ChangeOrderStatusRequest $request,
        Order $order,
        ChangeOrderStatus $action,
    ): RedirectResponse {
        $action->execute($order, OrderStatus::from($request->validated('status')), $request->user());

        return back()->with('success', 'Order status updated successfully.');
    }
}
