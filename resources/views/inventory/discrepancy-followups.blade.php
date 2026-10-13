@extends('layouts.app')
@section('title', 'Corrective follow-ups · SellAssist KH')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="h3 mb-1">Corrective follow-ups</h1><p class="text-secondary mb-0">Assigned actions for reviewed stock discrepancies. These tasks do not change stock.</p></div>
    @can('inventory.review')<a href="{{ route('inventory.discrepancies.index') }}">Discrepancy reviews</a>@endcan
</div>
<form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-sm-4"><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-select"><option value="">All statuses</option>@foreach($statuses as $choice)<option value="{{ $choice->value }}" @selected($status === $choice->value)>{{ $choice->label() }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Action</th><th>Product</th><th>Assignee</th><th>Due</th><th>Status</th></tr></thead>
    <tbody>@forelse($followups as $followup)
        <tr><td><a href="{{ route('inventory.followups.show', $followup) }}">{{ $followup->title }}</a></td><td>{{ $followup->review->stockCount->product->name }}@if($followup->review->stockCount->variant) · {{ $followup->review->stockCount->variant->display_name }}@endif</td><td>{{ $followup->assignee->name }}</td><td>{{ $followup->due_on?->format('d M Y') ?? '—' }}</td><td>{{ $followup->status->label() }}</td></tr>
    @empty<tr><td colspan="5" class="text-secondary">No follow-ups found.</td></tr>@endforelse</tbody>
</table></div></div>
<div class="mt-3">{{ $followups->links() }}</div>
@endsection
