<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Delivery\Models\Shipment;
use App\Jobs\SubmitShipmentToProvider;
use App\Jobs\SyncShipmentFromProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShipmentIntegrationController extends Controller
{
    public function submit(Request $request, Shipment $shipment): RedirectResponse
    {
        abort_unless($request->user()?->can('delivery.update'), 403);
        SubmitShipmentToProvider::dispatch($shipment->id, $request->user()->id)->onQueue('integrations');

        return back()->with('success', 'Shipment submission queued.');
    }

    public function sync(Request $request, Shipment $shipment): RedirectResponse
    {
        abort_unless($request->user()?->can('delivery.update'), 403);
        SyncShipmentFromProvider::dispatch($shipment->id, $request->user()->id)->onQueue('integrations');

        return back()->with('success', 'Provider status synchronization queued.');
    }
}
