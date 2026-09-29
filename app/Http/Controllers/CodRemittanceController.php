<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Delivery\Actions\RecordCodRemittance;
use App\Domain\Delivery\Models\Shipment;
use App\Http\Requests\Delivery\StoreCodRemittanceRequest;
use Illuminate\Http\RedirectResponse;

class CodRemittanceController extends Controller
{
    public function store(
        StoreCodRemittanceRequest $request,
        Shipment $shipment,
        RecordCodRemittance $action,
    ): RedirectResponse {
        $action->execute($shipment, $request->validated(), $request->user());

        return back()->with('success', 'COD remittance recorded successfully.');
    }
}
