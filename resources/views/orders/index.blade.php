@extends('layouts.app')
@section('title', 'Orders · SellAssist KH')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="h3 mb-1">Orders</h1><p class="text-secondary mb-0">Find orders that need packing, shipping, or payment.</p></div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('orders.export', $filters) }}">Export filtered CSV</a>
        @can('orders.create')<a class="btn btn-primary" href="{{ route('orders.create') }}">New order</a>@endcan
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3" aria-label="Quick order filters">
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('orders.index') }}">All orders</a>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('orders.index', $quickFilters['unpaid']) }}">Unpaid</a>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('orders.index', $quickFilters['readyToPack']) }}">Ready to pack</a>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('orders.index', $quickFilters['readyToShip']) }}">Ready to ship</a>
</div>

<div class="card">
    <div class="card-body border-bottom">
        <form class="row g-2 align-items-end" method="GET" action="{{ route('orders.index') }}">
            <div class="col-12 col-lg-4">
                <label class="form-label" for="order-search">Search</label>
                <input id="order-search" class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="Order number, customer, or phone">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="order-status">Status</label>
                <select id="order-status" class="form-select" name="status">
                    <option value="">All statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="order-payment-status">Payment</label>
                <select id="order-payment-status" class="form-select" name="payment_status">
                    <option value="">All payments</option>
                    @foreach($paymentStatuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['payment_status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="order-source">Source</label>
                <select id="order-source" class="form-select" name="source">
                    <option value="">All sources</option>
                    @foreach($sources as $source)
                        <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ $source->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="order-currency">Currency</label>
                <select id="order-currency" class="form-select" name="currency">
                    <option value="">All currencies</option>
                    @foreach($currencies as $currency)
                        <option value="{{ $currency->value }}" @selected(($filters['currency'] ?? '') === $currency->value)>{{ $currency->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="order-created-from">From</label>
                <input id="order-created-from" type="date" class="form-control" name="created_from" value="{{ $filters['created_from'] ?? '' }}">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label" for="order-created-to">To</label>
                <input id="order-created-to" type="date" class="form-control" name="created_to" value="{{ $filters['created_to'] ?? '' }}">
            </div>
            <div class="col-12 col-lg-3 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit">Apply filters</button>
                <a class="btn btn-outline-secondary" href="{{ route('orders.index') }}">Clear</a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Order</th><th>Customer</th><th>Source</th><th>Status</th><th>Payment</th><th class="text-end">Total</th></tr></thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td><a class="fw-semibold text-decoration-none" href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a><div class="small text-secondary">{{ $order->created_at->format('d M Y H:i') }}</div></td>
                        <td>{{ $order->customer_name ?: ($order->customer?->name ?? '') }}<div class="small text-secondary">{{ $order->customer_phone ?: ($order->customer?->phone ?? '') }}</div></td>
                        <td>{{ $order->source->label() }}</td>
                        <td><span class="badge text-bg-secondary">{{ $order->status->label() }}</span></td>
                        <td>{{ $order->payment_status->label() }}</td>
                        <td class="text-end">{{ $order->currency->format($order->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">No orders found for these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())<div class="card-footer bg-white">{{ $orders->links() }}</div>@endif
</div>
@endsection
