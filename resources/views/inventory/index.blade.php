@extends('layouts.app')
@section('title', 'Inventory · SellAssist KH')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="h3 mb-1">Inventory</h1><p class="text-secondary mb-0">Every stock change is recorded in the audit trail.</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('inventory.low-stock') }}">View low-stock items</a>
</div>

<div class="row g-4">
    @can('inventory.adjust')
        <div class="col-lg-4">
            <div class="card"><div class="card-body">
                <h2 class="h5">Adjust stock</h2>
                <form method="POST" action="{{ route('inventory.adjustments.store') }}" x-data="inventoryForm(@js($catalog))">
                    @csrf
                    <div class="mb-3"><label class="form-label">Product *</label><select class="form-select" name="product_id" x-model="productId" @change="variantId = ''" required><option value="">Choose a product</option><template x-for="product in products" :key="product.id"><option :value="product.id" x-text="`${product.name} (${product.stock})`"></option></template></select></div>
                    <div class="mb-3" x-show="variants.length" x-cloak><label class="form-label">Variant *</label><select class="form-select" name="product_variant_id" x-model="variantId" :required="variants.length > 0"><option value="">Choose a variant</option><template x-for="variant in variants" :key="variant.id"><option :value="variant.id" x-text="`${variant.name} (${variant.stock})`"></option></template></select></div>
                    <div class="mb-3"><label class="form-label">Movement *</label><select class="form-select" name="type" required><option value="stock_in">Stock in</option><option value="stock_out">Stock out</option><option value="adjustment">Adjustment (+ or −)</option></select></div>
                    <div class="mb-3"><label class="form-label">Quantity *</label><input class="form-control" type="number" name="quantity" value="{{ old('quantity', 1) }}" required><div class="form-text">Stock in/out accepts a positive quantity. Adjustment may be positive or negative.</div></div>
                    <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2">{{ old('notes') }}</textarea></div>
                    <button class="btn btn-primary w-100">Record adjustment</button>
                </form>
            </div></div>
        </div>
    @endcan

    <div class="{{ auth()->user()->can('inventory.adjust') ? 'col-lg-8' : 'col-12' }}">
        <div class="card">
            <div class="card-header bg-white"><strong>Stock movement history</strong></div>
            <div class="card-body border-bottom">
                @if($selectedProduct)
                    <p class="small mb-2">Showing movements for <strong>{{ $selectedProduct->name }}</strong>.</p>
                @endif
                @if($selectedVariant)
                    <p class="small mb-2">Variant: <strong>{{ $selectedVariant->display_name }}</strong>.</p>
                @endif
                <form class="row g-2 align-items-end" method="GET" action="{{ route('inventory.index') }}">
                    @if($selectedProduct)<input type="hidden" name="product" value="{{ $selectedProduct->uuid }}">@endif
                    @if($selectedVariant)<input type="hidden" name="variant" value="{{ $selectedVariant->uuid }}">@endif
                    <div class="col-12 col-md-4"><label class="form-label" for="movement-search">Item</label><input id="movement-search" class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="Product name or SKU"></div>
                    <div class="col-6 col-md-2"><label class="form-label" for="movement-type">Type</label><select id="movement-type" class="form-select" name="type"><option value="">All types</option>@foreach($movementTypes as $type)<option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>@endforeach</select></div>
                    <div class="col-6 col-md-2"><label class="form-label" for="movement-from">From</label><input id="movement-from" class="form-control" type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}"></div>
                    <div class="col-6 col-md-2"><label class="form-label" for="movement-to">To</label><input id="movement-to" class="form-control" type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}"></div>
                    <div class="col-6 col-md-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit">Filter</button><a class="btn btn-outline-secondary" href="{{ route('inventory.index') }}">Clear</a></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Item</th><th>Type</th><th>Change</th><th>Before → after</th><th>By</th><th>Date</th></tr></thead>
                    <tbody>
                        @forelse($movements as $movement)
                            <tr>
                                <td>@can('products.view')<a href="{{ route('products.show', $movement->product) }}">{{ $movement->product->name }}</a>@else{{ $movement->product->name }}@endcan<div class="small text-secondary">{{ $movement->variant?->display_name }}</div></td>
                                <td>{{ $movement->type->label() }}@if($movement->notes)<div class="small text-secondary">{{ $movement->notes }}</div>@endif</td>
                                <td class="fw-semibold {{ $movement->quantity > 0 ? 'text-success' : 'text-danger' }}">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                                <td>{{ $movement->quantity_before }} → {{ $movement->quantity_after }}</td>
                                <td>{{ $movement->creator?->name ?: 'System' }}</td>
                                <td>{{ $movement->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">No stock movements match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($movements->hasPages())<div class="card-footer bg-white">{{ $movements->links() }}</div>@endif
        </div>
    </div>
</div>
<script>
document.addEventListener('alpine:init', () => Alpine.data('inventoryForm', (products) => ({
    products,
    productId: @js((string) old('product_id', '')),
    variantId: @js((string) old('product_variant_id', '')),
    get variants() {
        return this.products.find(product => Number(product.id) === Number(this.productId))?.variants ?? [];
    },
})));
</script>
@endsection
