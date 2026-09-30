@extends('layouts.app')

@section('title', 'Dashboard · SellAssist KH')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="h3 mb-1">Dashboard</h1><p class="text-secondary mb-0">Today’s selling activity at a glance.</p></div>
    @can('orders.create')<a class="btn btn-primary" href="{{ route('orders.create') }}">New order</a>@endcan
</div>

<div class="row g-3 mb-4">
    @foreach ([
        ['Orders today', $metrics['orders_today']], ['Confirmed today', $metrics['confirmed_today']],
        ['Completed today', $metrics['completed_today']], ['Cancelled today', $metrics['cancelled_today']],
    ] as [$label, $value])
        <div class="col-6 col-lg-3"><div class="card metric-card h-100"><div class="card-body"><div class="text-secondary small">{{ $label }}</div><div class="metric-value">{{ number_format($value) }}</div></div></div></div>
    @endforeach
    <div class="col-12 col-md-6"><div class="card metric-card h-100"><div class="card-body"><div class="text-secondary small">Today's completed sales</div><div class="metric-value">{{ App\Domain\Payment\Enums\Currency::Usd->format($metrics['sales_today']['USD']) }}</div><div class="text-secondary">{{ App\Domain\Payment\Enums\Currency::Khr->format($metrics['sales_today']['KHR']) }}</div></div></div></div>
    <div class="col-12 col-md-6"><div class="card metric-card h-100"><div class="card-body"><div class="text-secondary small">Outstanding order balance</div><div class="metric-value text-danger">{{ App\Domain\Payment\Enums\Currency::Usd->format($metrics['unpaid_amount']['USD']) }}</div><div class="text-secondary">{{ App\Domain\Payment\Enums\Currency::Khr->format($metrics['unpaid_amount']['KHR']) }}</div></div></div></div>
    @can('delivery.view')<div class="col-12 col-md-6"><div class="card metric-card h-100"><div class="card-body"><div class="text-secondary small">Active shipments</div><div class="metric-value">{{ number_format($metrics['active_shipments']) }}</div><a href="{{ route('delivery.shipments.index') }}">View delivery queue</a></div></div></div>@endcan
    @can('delivery.cod.reconcile')<div class="col-12 col-md-6"><div class="card metric-card h-100"><div class="card-body"><div class="text-secondary small">Collected COD awaiting remittance</div><div class="metric-value text-danger">USD {{ number_format($metrics['cod_outstanding']['USD'], 2) }}</div><div class="text-secondary">KHR {{ number_format($metrics['cod_outstanding']['KHR'], 2) }}</div></div></div></div>@endcan
    @can('social.view')<div class="col-12 col-md-6"><div class="card metric-card h-100"><div class="card-body"><div class="text-secondary small">Open social conversations</div><div class="metric-value">{{ number_format($metrics['open_social_conversations']) }}</div><a href="{{ route('social.inbox.index') }}">Review inbox</a></div></div></div>@endcan
    @can('operations.view')<div class="col-12 col-md-6"><div class="card metric-card h-100"><div class="card-body"><div class="text-secondary small">Failed background jobs</div><div class="metric-value {{ $metrics['failed_jobs'] > 0 ? 'text-danger' : '' }}">{{ number_format($metrics['failed_jobs']) }}</div><a href="{{ route('operations.index') }}">Open operations health</a></div></div></div>@endcan
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card"><div class="card-header bg-white d-flex justify-content-between align-items-center"><strong>Recent orders</strong><a href="{{ route('orders.index') }}">View all</a></div>
            <div class="table-responsive"><table class="table table-hover mb-0">
                <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                @forelse($recentOrders as $order)
                    <tr><td><a href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a><div class="small text-secondary">{{ $order->created_at->diffForHumans() }}</div></td><td>{{ $order->customer->name }}</td><td><span class="badge text-bg-secondary">{{ $order->status->label() }}</span></td><td class="text-end">{{ $order->currency->format($order->total) }}</td></tr>
                @empty<tr><td colspan="4" class="text-center text-secondary py-4">No orders yet.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-header bg-white"><strong>Low stock</strong></div><div class="list-group list-group-flush">
            @forelse($lowStockProducts as $product)
                <a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('products.show', $product) }}"><span>{{ $product->name }}</span><span class="badge text-bg-warning">{{ $product->stock_quantity }}</span></a>
            @empty @endforelse
            @foreach($lowStockVariants as $variant)
                <a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('products.show', $variant->product) }}"><span>{{ $variant->product->name }} <small class="text-secondary">{{ $variant->display_name }}</small></span><span class="badge text-bg-warning">{{ $variant->stock_quantity }}</span></a>
            @endforeach
            @if($lowStockProducts->isEmpty() && $lowStockVariants->isEmpty())<div class="list-group-item text-secondary">No low-stock items.</div>@endif
        </div></div>
    </div>
</div>
@endsection

