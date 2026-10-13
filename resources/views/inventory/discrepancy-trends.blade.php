@extends('layouts.app')
@section('title', 'Discrepancy trends · SellAssist KH')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div><a href="{{ route('inventory.discrepancies.index') }}">← Discrepancy reviews</a><h1 class="h3 mt-2 mb-1">Discrepancy trends</h1><p class="text-secondary mb-0">Aggregate physical-count differences since {{ $report['from']->format('d M Y') }}. Counts and reviews remain unchanged.</p></div>
    <form method="GET" action="{{ route('inventory.discrepancies.trends') }}"><label class="form-label" for="trend-window">Period</label><select id="trend-window" class="form-select" name="window" onchange="this.form.submit()">@foreach($windows as $option)<option value="{{ $option->value }}" @selected($report['window'] === $option)>{{ $option->label() }}</option>@endforeach</select></form>
</div>

<div class="row g-3 mb-4">
    <div class="col-4"><div class="card"><div class="card-body"><div class="small text-secondary">Discrepancies</div><div class="h4 mb-0">{{ $report['total'] }}</div></div></div></div>
    <div class="col-4"><div class="card"><div class="card-body"><div class="small text-secondary">Pending review</div><div class="h4 mb-0">{{ $report['pending'] }}</div></div></div></div>
    <div class="col-4"><div class="card"><div class="card-body"><div class="small text-secondary">Reviewed</div><div class="h4 mb-0">{{ $report['reviewed'] }}</div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-7"><div class="card"><div class="card-header bg-white"><strong>Daily discrepancies</strong></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Count date</th><th class="text-end">Counts</th><th class="text-end">Absolute units</th><th></th></tr></thead><tbody>
        @forelse($report['daily'] as $day)<tr><td>{{ $day->day }}</td><td class="text-end">{{ $day->discrepancy_count }}</td><td class="text-end">{{ $day->absolute_units }}</td><td><a href="{{ route('inventory.discrepancies.index', ['created_from' => $day->day, 'created_to' => $day->day]) }}">View</a></td></tr>
        @empty<tr><td colspan="4" class="text-center text-secondary py-4">No discrepancies in this period.</td></tr>@endforelse
    </tbody></table></div></div></div>
    <div class="col-lg-5"><div class="card"><div class="card-header bg-white"><strong>Reviewed causes</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Cause</th><th class="text-end">Reviews</th></tr></thead><tbody>
        @forelse($report['causes'] as $cause)<tr><td>{{ $cause->root_cause?->label() ?? 'Legacy review (uncategorized)' }}</td><td class="text-end">{{ $cause->review_count }}</td></tr>
        @empty<tr><td colspan="2" class="text-center text-secondary py-4">No reviewed causes in this period.</td></tr>@endforelse
    </tbody></table></div></div></div>
    <div class="col-12"><div class="card"><div class="card-header bg-white"><strong>Products with the most discrepancies</strong></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Product</th><th class="text-end">Counts</th><th class="text-end">Absolute units</th><th></th></tr></thead><tbody>
        @forelse($report['topProducts'] as $product)<tr><td>{{ $product->product->name }}</td><td class="text-end">{{ $product->discrepancy_count }}</td><td class="text-end">{{ $product->absolute_units }}</td><td><a href="{{ route('inventory.discrepancies.index', ['product' => $product->product->uuid]) }}">View</a></td></tr>
        @empty<tr><td colspan="4" class="text-center text-secondary py-4">No products with discrepancies in this period.</td></tr>@endforelse
    </tbody></table></div></div></div>
</div>
<p class="small text-secondary mt-3">Absolute units are the sum of each count's unsigned difference; opposite adjustments do not cancel each other out. Causes are supervisors' recorded classifications, not automatic diagnoses. No customer content or encrypted review notes appear here.</p>
@endsection
