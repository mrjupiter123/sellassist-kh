@extends('layouts.app')
@section('title', 'Stock discrepancy reviews · SellAssist KH')
@section('content')
<div class="mb-4"><a href="{{ route('inventory.stock-counts.index') }}">← Physical counts</a><h1 class="h3 mt-2 mb-1">Stock discrepancy reviews</h1><p class="text-secondary mb-0">Supervisor acknowledgement only. Reviewing never changes stock or rewrites the original count.</p></div>

<div class="row g-3 mb-4">
    <div class="col-4"><div class="card"><div class="card-body"><div class="small text-secondary">Discrepancies</div><div class="h4 mb-0">{{ $summary['total'] }}</div></div></div></div>
    <div class="col-4"><div class="card"><div class="card-body"><div class="small text-secondary">Pending</div><div class="h4 mb-0">{{ $summary['pending'] }}</div></div></div></div>
    <div class="col-4"><div class="card"><div class="card-body"><div class="small text-secondary">Reviewed</div><div class="h4 mb-0">{{ $summary['reviewed'] }}</div></div></div></div>
</div>
<p class="small text-secondary">Totals follow the item and date filters; the review-state filter applies to the table below.</p>

<div class="card">
    <div class="card-body border-bottom">
        @if($selectedProduct)<p class="small mb-2">Showing discrepancies for <strong>{{ $selectedProduct->name }}</strong>.</p>@endif
        <form class="row g-2 align-items-end" method="GET" action="{{ route('inventory.discrepancies.index') }}">
            @if(! empty($filters['product']))<input type="hidden" name="product" value="{{ $filters['product'] }}">@endif
            <div class="col-12 col-md-4"><label class="form-label" for="discrepancy-search">Item</label><input id="discrepancy-search" class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="Product name or SKU"></div>
            <div class="col-6 col-md-2"><label class="form-label" for="discrepancy-review">Review</label><select id="discrepancy-review" class="form-select" name="reviewed"><option value="">All</option>@foreach($reviewFilters as $option)<option value="{{ $option->value }}" @selected(($filters['reviewed'] ?? '') === $option->value)>{{ $option->label() }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label" for="discrepancy-from">From</label><input id="discrepancy-from" class="form-control" type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}"></div>
            <div class="col-6 col-md-2"><label class="form-label" for="discrepancy-to">To</label><input id="discrepancy-to" class="form-control" type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}"></div>
            <div class="col-6 col-md-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit">Filter</button><a class="btn btn-outline-secondary" href="{{ route('inventory.discrepancies.index') }}">Clear</a></div>
        </form>
    </div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Item</th><th class="text-end">Expected</th><th class="text-end">Counted</th><th class="text-end">Difference</th><th>Counted by</th><th>Review</th><th>Date</th><th></th></tr></thead>
        <tbody>@forelse($counts as $count)
            <tr><td>{{ $count->product->name }}@if($count->variant)<div class="small text-secondary">{{ $count->variant->display_name }}</div>@endif</td><td class="text-end">{{ $count->expected_quantity }}</td><td class="text-end">{{ $count->counted_quantity }}</td><td class="text-end {{ $count->difference < 0 ? 'text-danger' : 'text-success' }}">{{ $count->difference > 0 ? '+' : '' }}{{ $count->difference }}</td><td>{{ $count->creator->name }}</td><td>@if($count->discrepancyReview)<span class="badge text-bg-success">Reviewed</span><div class="small text-secondary">{{ $count->discrepancyReview->reviewer->name }}</div>@else<span class="badge text-bg-warning">Pending</span>@endif</td><td>{{ $count->created_at->format('d M Y') }}</td><td><a href="{{ route('inventory.stock-counts.show', $count) }}">View</a></td></tr>
        @empty<tr><td colspan="8" class="text-center text-secondary py-4">No discrepancies match these filters.</td></tr>@endforelse</tbody>
    </table></div>
    @if($counts->hasPages())<div class="card-footer bg-white">{{ $counts->links() }}</div>@endif
</div>
@endsection
