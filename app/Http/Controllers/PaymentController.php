<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Actions\RecordPayment;
use App\Http\Requests\Payment\StorePaymentRequest;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Order $order, RecordPayment $action): RedirectResponse
    {
        $action->execute($order, $request->validated(), $request->user());

        return back()->with('success', 'Payment recorded successfully.');
    }
}
