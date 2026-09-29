<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Delivery\Actions\CreateShipment;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Models\Shipment;
use App\Domain\Delivery\Services\ShipmentStatusTransition;
use App\Domain\Order\Models\Order;
use App\Http\Requests\Delivery\StoreShipmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(Request $request): View
    {
        $shipments = Shipment::query()
            ->with(['order:id,uuid,order_number,customer_name', 'provider:id,uuid,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->query('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.addcslashes(trim((string) $request->query('search')), '%_\\').'%';
                $query->where(fn ($query) => $query->where('shipment_number', 'like', $term)
                    ->orWhere('tracking_number', 'like', $term)
                    ->orWhereHas('order', fn ($query) => $query->where('order_number', 'like', $term)));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('delivery.shipments.index', ['shipments' => $shipments, 'statuses' => ShipmentStatus::cases()]);
    }

    public function store(StoreShipmentRequest $request, Order $order, CreateShipment $action): RedirectResponse
    {
        $shipment = $action->execute($order, $request->validated(), $request->user());

        return redirect()->route('delivery.shipments.show', $shipment)->with('success', 'Shipment created successfully.');
    }

    public function show(Shipment $shipment, ShipmentStatusTransition $transitions): View
    {
        $shipment->load([
            'order.items', 'order.customer', 'provider', 'creator',
            'histories.creator', 'remittances.creator', 'remittances.payment',
            'integrationLogs',
        ]);

        return view('delivery.shipments.show', [
            'shipment' => $shipment,
            'nextStatuses' => $transitions->allowedFrom($shipment->status),
            'remittedTotal' => (float) $shipment->remittances->sum('amount'),
            'codBalance' => max(0, (float) $shipment->cod_amount - (float) $shipment->remittances->sum('amount')),
        ]);
    }

    public function label(Shipment $shipment): View
    {
        $shipment->load(['order.items', 'provider']);

        return view('delivery.shipments.label', compact('shipment'));
    }
}
