@extends('layouts.app')
@section('title', 'Delivery providers · SellAssist KH')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><div><a href="{{ route('delivery.shipments.index') }}">← Delivery</a><h1 class="h3 mt-2 mb-1">Delivery providers</h1><p class="text-secondary mb-0">Maintain the couriers available to shipment entry.</p></div><a class="btn btn-primary" href="{{ route('delivery.providers.create') }}">Add provider</a></div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Provider</th><th>Code</th><th>Phone</th><th>Shipments</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($providers as $provider)<tr><td class="fw-semibold">{{ $provider->name }}</td><td>{{ $provider->code }}</td><td>{{ $provider->contact_phone ?: '—' }}</td><td>{{ $provider->shipments_count }}</td><td><span class="badge {{ $provider->active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $provider->active ? 'Active' : 'Inactive' }}</span></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('delivery.providers.edit', $provider) }}">Edit</a></td></tr>
@empty<tr><td colspan="6" class="text-center text-secondary py-4">No delivery providers.</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $providers->links() }}</div>
@endsection
