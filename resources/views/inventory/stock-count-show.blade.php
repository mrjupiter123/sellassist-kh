@extends('layouts.app')
@section('title', 'Physical count · SellAssist KH')
@section('content')
<div class="mb-4">
    @can('inventory.adjust')<a href="{{ route('inventory.stock-counts.index') }}">← Physical stock counts</a>@else<a href="{{ route('inventory.discrepancies.index') }}">← Discrepancy reviews</a>@endcan
    <h1 class="h3 mt-2 mb-1">Physical count</h1>
    <p class="text-secondary mb-0">Recorded {{ $count->created_at->format('d M Y H:i') }} by {{ $count->creator->name }}. This record cannot be edited.</p>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <h2 class="h5">Count details</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">Product</dt><dd class="col-sm-8">@can('products.view')<a href="{{ route('products.show', $count->product) }}">{{ $count->product->name }}</a>@else{{ $count->product->name }}@endcan</dd>
                @if($count->variant)<dt class="col-sm-4">Variant</dt><dd class="col-sm-8">{{ $count->variant->display_name }}</dd>@endif
                <dt class="col-sm-4">Expected stock</dt><dd class="col-sm-8">{{ $count->expected_quantity }}</dd>
                <dt class="col-sm-4">Physically counted</dt><dd class="col-sm-8">{{ $count->counted_quantity }}</dd>
                <dt class="col-sm-4">Difference</dt><dd class="col-sm-8 {{ $count->difference < 0 ? 'text-danger' : ($count->difference > 0 ? 'text-success' : '') }}">{{ $count->difference > 0 ? '+' : '' }}{{ $count->difference }}</dd>
                @if($count->notes)<dt class="col-sm-4">Notes</dt><dd class="col-sm-8">{{ $count->notes }}</dd>@endif
            </dl>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <h2 class="h5">Stock audit</h2>
            @if($count->movement)
                <p class="mb-2">An adjustment movement was recorded with this count.</p>
                <p class="mb-0">{{ $count->movement->quantity_before }} → {{ $count->movement->quantity_after }}
                    ({{ $count->movement->quantity > 0 ? '+' : '' }}{{ $count->movement->quantity }})</p>
                @can('inventory.view')<a class="d-inline-block mt-3" href="{{ route('inventory.index', ['product' => $count->product->uuid, 'variant' => $count->variant?->uuid, 'type' => $count->movement->type->value]) }}">View stock movement history</a>@endcan
            @else
                <p class="mb-0">No adjustment was needed because the physical count matched the recorded stock.</p>
            @endif
        </div></div>
    </div>
</div>
@can('inventory.review')
    @if($count->difference !== 0)
        <div class="card mt-4"><div class="card-body">
            <h2 class="h5">Supervisor discrepancy review</h2>
            @if($count->discrepancyReview)
                <p class="mb-2">Reviewed by {{ $count->discrepancyReview->reviewer->name }} on {{ $count->discrepancyReview->reviewed_at->format('d M Y H:i') }}.</p>
                <p class="mb-2">Cause: {{ $count->discrepancyReview->root_cause?->label() ?? 'Legacy review (uncategorized)' }}</p>
                <p class="mb-0">{{ $count->discrepancyReview->review_notes }}</p>
            @else
                <p class="small text-secondary">Acknowledge the discrepancy after checking the count and movement. This does not change inventory, and the review cannot be edited.</p>
                <form method="POST" action="{{ route('inventory.discrepancies.review', $count) }}">
                    @csrf
                    <div class="mb-3"><label class="form-label" for="review-cause">Likely cause *</label><select id="review-cause" class="form-select" name="root_cause" required><option value="">Choose a cause</option>@foreach($discrepancyCauses as $cause)<option value="{{ $cause->value }}" @selected(old('root_cause') === $cause->value)>{{ $cause->label() }}</option>@endforeach</select><div class="form-text">Choose Unexplained if the cause is not yet known.</div></div>
                    <div class="mb-3"><label class="form-label" for="review-notes">Review notes *</label><textarea id="review-notes" class="form-control" name="review_notes" rows="3" minlength="5" maxlength="2000" required>{{ old('review_notes') }}</textarea><div class="form-text">Describe what was checked. Do not include customer data or credentials.</div></div>
                    <button class="btn btn-primary" type="submit">Record review</button>
                </form>
            @endif
        </div></div>
    @endif
@endcan
@endsection
