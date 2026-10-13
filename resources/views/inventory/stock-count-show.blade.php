@extends('layouts.app')
@section('title', 'Physical count · SellAssist KH')
@section('content')
<div class="mb-4">
    <a href="{{ route('inventory.stock-counts.index') }}">← Physical stock counts</a>
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
@endsection
