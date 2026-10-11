@extends('layouts.app')
@section('title', 'Low stock · SellAssist KH')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><a href="{{ route('inventory.index') }}">← Inventory</a><h1 class="h3 mt-2 mb-1">Low-stock items</h1><p class="text-secondary mb-0">Active items at or below their alert threshold. Stock changes still go through the inventory adjustment form.</p></div>
    @can('inventory.adjust')<a class="btn btn-primary" href="{{ route('inventory.index') }}">Adjust stock</a>@endcan
</div>

<div class="row g-4">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header bg-white"><strong>Products without variants</strong> <span class="text-secondary">({{ $products->total() }})</span></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Product</th><th class="text-end">Stock</th><th class="text-end">Threshold</th></tr></thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr><td>@can('products.view')<a href="{{ route('products.show', $product) }}">{{ $product->name }}</a>@else{{ $product->name }}@endcan<div class="small text-secondary">{{ $product->sku }}</div></td><td class="text-end"><span class="badge text-bg-warning">{{ $product->stock_quantity }}</span></td><td class="text-end">{{ $product->low_stock_threshold }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-secondary py-4">No low-stock standalone products.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($products->hasPages())<div class="card-footer bg-white">{{ $products->links() }}</div>@endif
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header bg-white"><strong>Variants</strong> <span class="text-secondary">({{ $variants->total() }})</span></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Variant</th><th class="text-end">Stock</th><th class="text-end">Threshold</th></tr></thead>
                    <tbody>
                        @forelse($variants as $variant)
                            <tr><td>@can('products.view')<a href="{{ route('products.show', $variant->product) }}">{{ $variant->product->name }}</a>@else{{ $variant->product->name }}@endcan<div class="small text-secondary">{{ $variant->display_name }}@if($variant->sku) · {{ $variant->sku }}@endif</div></td><td class="text-end"><span class="badge text-bg-warning">{{ $variant->stock_quantity }}</span></td><td class="text-end">{{ $variant->low_stock_threshold }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-secondary py-4">No low-stock variants.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($variants->hasPages())<div class="card-footer bg-white">{{ $variants->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
