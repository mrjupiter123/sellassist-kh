@extends('layouts.app')
@section('title', 'Edit variant · SellAssist KH')
@section('content')
<div class="mb-4"><a href="{{ route('products.show', $product) }}">← {{ $product->name }}</a><h1 class="h3 mt-2">Edit variant</h1><p class="text-secondary">Stock is changed separately through Inventory so every adjustment remains auditable.</p></div>
<div class="card"><div class="card-body"><form method="POST" action="{{ route('products.variants.update', [$product, $variant]) }}">@csrf @method('PUT')
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Color</label><input class="form-control" name="color" value="{{ old('color', $variant->color) }}"></div>
        <div class="col-md-6"><label class="form-label">Size</label><input class="form-control" name="size" value="{{ old('size', $variant->size) }}"></div>
        <div class="col-md-6"><label class="form-label">SKU</label><input class="form-control" name="sku" value="{{ old('sku', $variant->sku) }}"></div>
        <div class="col-md-3"><label class="form-label">Price override</label><input class="form-control" type="number" step="0.01" min="0" name="price" value="{{ old('price', $variant->price) }}"></div>
        <div class="col-md-3"><label class="form-label">Cost</label><input class="form-control" type="number" step="0.01" min="0" name="cost" value="{{ old('cost', $variant->cost) }}"></div>
        <div class="col-md-6"><label class="form-label">Low-stock threshold *</label><input class="form-control" type="number" min="0" name="low_stock_threshold" value="{{ old('low_stock_threshold', $variant->low_stock_threshold) }}" required></div>
        <div class="col-md-6 d-flex align-items-end"><input type="hidden" name="active" value="0"><div class="form-check mb-2"><input class="form-check-input" id="active" type="checkbox" name="active" value="1" @checked((bool) old('active', $variant->active))><label class="form-check-label" for="active">Active for new orders</label></div></div>
    </div><button class="btn btn-primary mt-4">Save variant</button>
</form></div></div>
@endsection
