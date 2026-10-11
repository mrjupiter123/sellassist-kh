@extends('layouts.app')
@section('title', $customer->name.' · SellAssist KH')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><a href="{{ route('customers.index') }}">← Customers</a><h1 class="h3 mt-2 mb-0">{{ $customer->name }}</h1></div>
    @can('customers.update')<a class="btn btn-outline-secondary" href="{{ route('customers.edit', $customer) }}">Edit customer</a>@endcan
</div>

<div class="row g-4">
    <div class="{{ $orders === null ? 'col-12' : 'col-lg-4' }}">
        <div class="card">
            <div class="card-body">
                <h2 class="h5">Contact details</h2>
                <dl class="row mb-0">
                    <dt class="col-5">Phone</dt><dd class="col-7">{{ $customer->phone ?: '—' }}</dd>
                    <dt class="col-5">Facebook</dt><dd class="col-7">{{ $customer->facebook_name ?: '—' }}</dd>
                    <dt class="col-5">Email</dt><dd class="col-7">{{ $customer->email ?: '—' }}</dd>
                    <dt class="col-5">Source</dt><dd class="col-7">{{ $customer->source->label() }}</dd>
                    <dt class="col-5">Location</dt><dd class="col-7">{{ collect([$customer->commune, $customer->district, $customer->province])->filter()->join(', ') ?: '—' }}</dd>
                </dl>
                @if($customer->address)<hr><p class="mb-0">{{ $customer->address }}</p>@endif
                @if($customer->notes)<hr><p class="small text-secondary mb-0">{{ $customer->notes }}</p>@endif
            </div>
        </div>
    </div>

    @if($orders !== null)
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white"><strong>Order history</strong> <span class="text-secondary">({{ $orders->total() }})</span></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Order</th><th>Status</th><th>Payment</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @forelse($orders as $order)
                                <tr>
                                    <td><a href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a><div class="small text-secondary">{{ $order->created_at->format('d M Y H:i') }}</div></td>
                                    <td>{{ $order->status->label() }}</td>
                                    <td>{{ $order->payment_status->label() }}</td>
                                    <td class="text-end">{{ $order->currency->format($order->total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-secondary py-4">No orders yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($orders->hasPages())<div class="card-footer bg-white">{{ $orders->links() }}</div>@endif
            </div>
        </div>
    @endif
</div>
@endsection
