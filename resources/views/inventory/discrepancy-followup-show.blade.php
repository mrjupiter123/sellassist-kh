@extends('layouts.app')
@section('title', 'Corrective follow-up · SellAssist KH')
@section('content')
<div class="mb-4"><a href="{{ route('inventory.followups.index') }}">← Corrective follow-ups</a><h1 class="h3 mt-2">{{ $followup->title }}</h1></div>
<div class="card"><div class="card-body">
    <dl class="row mb-0">
        <dt class="col-sm-3">Product</dt><dd class="col-sm-9">{{ $followup->review->stockCount->product->name }}@if($followup->review->stockCount->variant) · {{ $followup->review->stockCount->variant->display_name }}@endif</dd>
        <dt class="col-sm-3">Count difference</dt><dd class="col-sm-9">{{ $followup->review->stockCount->difference }}</dd>
        <dt class="col-sm-3">Assigned to</dt><dd class="col-sm-9">{{ $followup->assignee->name }}</dd>
        <dt class="col-sm-3">Due date</dt><dd class="col-sm-9">{{ $followup->due_on?->format('d M Y') ?? 'No due date' }}</dd>
        <dt class="col-sm-3">Status</dt><dd class="col-sm-9">{{ $followup->status->label() }}</dd>
        <dt class="col-sm-3">Created by</dt><dd class="col-sm-9">{{ $followup->creator->name }} on {{ $followup->created_at->format('d M Y H:i') }}</dd>
        @if($followup->completed_at)
            <dt class="col-sm-3">Completed by</dt><dd class="col-sm-9">{{ $followup->completer->name }} on {{ $followup->completed_at->format('d M Y H:i') }}</dd>
            <dt class="col-sm-3">Outcome</dt><dd class="col-sm-9">{{ $followup->outcome_note }}</dd>
        @endif
    </dl>
    @can('inventory.review')<a href="{{ route('inventory.stock-counts.show', $followup->review->stockCount) }}">View supervisor review and stock audit</a>@endcan
</div></div>
@if($followup->status === \App\Domain\Inventory\Enums\DiscrepancyFollowupStatus::Open && (auth()->user()->can('inventory.review') || $followup->assigned_to === auth()->id()))
<div class="card mt-4"><div class="card-body">
    <h2 class="h5">Complete follow-up</h2>
    <p class="text-secondary small">Describe the investigation and resolution. This records an outcome only; any stock correction must be made through the separate inventory adjustment workflow.</p>
    <form method="POST" action="{{ route('inventory.followups.complete', $followup) }}">@csrf
        <div class="mb-3"><label for="outcome-note" class="form-label">Outcome note *</label><textarea id="outcome-note" class="form-control" name="outcome_note" rows="4" minlength="5" maxlength="2000" required>{{ old('outcome_note') }}</textarea></div>
        <button class="btn btn-primary">Mark complete</button>
    </form>
</div></div>
@endif
@endsection
