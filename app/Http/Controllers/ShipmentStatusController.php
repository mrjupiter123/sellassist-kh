<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Delivery\Actions\ChangeShipmentStatus;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Models\Shipment;
use App\Http\Requests\Delivery\ChangeShipmentStatusRequest;
use Illuminate\Http\RedirectResponse;

class ShipmentStatusController extends Controller
{
    public function update(
        ChangeShipmentStatusRequest $request,
        Shipment $shipment,
        ChangeShipmentStatus $action,
    ): RedirectResponse {
        $action->execute(
            $shipment,
            ShipmentStatus::from($request->validated('status')),
            $request->user(),
            $request->validated('notes'),
            $request->validated('tracking_number'),
        );

        return back()->with('success', 'Shipment status updated successfully.');
    }
}
