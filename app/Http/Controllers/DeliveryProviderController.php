<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Delivery\Actions\CreateDeliveryProvider;
use App\Domain\Delivery\Actions\UpdateDeliveryProvider;
use App\Domain\Delivery\Enums\DeliveryAdapter;
use App\Domain\Delivery\Models\DeliveryProvider;
use App\Http\Requests\Delivery\StoreDeliveryProviderRequest;
use App\Http\Requests\Delivery\UpdateDeliveryProviderRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DeliveryProviderController extends Controller
{
    public function index(): View
    {
        return view('delivery.providers.index', [
            'providers' => DeliveryProvider::query()->withCount('shipments')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('delivery.providers.form', ['provider' => null, 'adapters' => DeliveryAdapter::cases()]);
    }

    public function store(StoreDeliveryProviderRequest $request, CreateDeliveryProvider $action): RedirectResponse
    {
        $action->execute($request->validated());

        return redirect()->route('delivery.providers.index')->with('success', 'Delivery provider created successfully.');
    }

    public function edit(DeliveryProvider $provider): View
    {
        return view('delivery.providers.form', ['provider' => $provider, 'adapters' => DeliveryAdapter::cases()]);
    }

    public function update(
        UpdateDeliveryProviderRequest $request,
        DeliveryProvider $provider,
        UpdateDeliveryProvider $action,
    ): RedirectResponse {
        $action->execute($provider, $request->validated());

        return redirect()->route('delivery.providers.index')->with('success', 'Delivery provider updated successfully.');
    }
}
