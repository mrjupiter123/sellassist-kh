<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Actions\RecordRefund;
use App\Domain\Payment\Models\Payment;
use App\Http\Requests\Payment\StoreRefundRequest;
use Illuminate\Http\RedirectResponse;

class RefundController extends Controller
{
    public function store(StoreRefundRequest $request, Order $order, RecordRefund $action): RedirectResponse
    {
        $data = $request->validated();
        $payment = Payment::query()->findOrFail($data['payment_id']);
        $action->execute($order, $payment, $data, $request->user());

        return back()->with('success', 'Refund recorded successfully.');
    }
}
