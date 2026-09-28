@extends('layouts.app')
@section('title', 'Inventory · SellAssist KH')
@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Inventory</h1><p class="text-secondary mb-0">Every change is recorded in the stock audit trail.</p></div>
<div class="row g-4">
    @can('inventory.adjust')
    <div class="col-lg-4"><div class="card"><div class="card-body"><h2 class="h5">Adjust stock</h2>
        <form method="POST" action="{{ route('inventory.adjustments.store') }}" x-data="inventoryForm(@js($catalog))">@csrf
            <div class="mb-3"><label class="form-label">Product *</label><select class="form-select" name="product_id" x-model="productId" @change="variantId = ''" required><option value="">Choose a product</option><template x-for="product in products" :key="product.id"><option :value="product.id" x-text="`${product.name} (${product.stock})`"></option></template></select></div>
            <div class="mb-3" x-show="variants.length" x-cloak><label class="form-label">Variant *</label><select class="form-select" name="product_variant_id" x-model="variantId" :required="variants.length > 0"><option value="">Choose a variant</option><template x-for="variant in variants" :key="variant.id"><option :value="variant.id" x-text="`${variant.name} (${variant.stock})`"></option></template></select></div>
            <div class="mb-3"><label class="form-label">Movement *</label><select class="form-select" name="type" required><option value="stock_in">Stock in</option><option value="stock_out">Stock out</option><option value="adjustment">Adjustment (+ or −)</option></select></div>
            <div class="mb-3"><label class="form-label">Quantity *</label><input class="form-control" type="number" name="quantity" value="{{ old('quantity', 1) }}" required><div class="form-text">Stock in/out accepts a positive quantity. Adjustment may be positive or negative.</div></div>
            <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2">{{ old('notes') }}</textarea></div>
            <button class="btn btn-primary w-100">Record adjustment</button>
        </form>
    </div></div></div>
    @endcan
    <div class="{{ auth()->user()->can('inventory.adjust') ? 'col-lg-8' : 'col-12' }}"><div class="card"><div class="card-header bg-white"><strong>Recent movements</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Item</th><th>Type</th><th>Change</th><th>Before → after</th><th>By</th><th>Date</th></tr></thead><tbody>
        @forelse($movements as $movement)<tr><td><a href="{{ route('products.show', $movement->product) }}">{{ $movement->product->name }}</a><div class="small text-secondary">{{ $movement->variant?->display_name }}</div></td><td>{{ $movement->type->label() }}</td><td class="fw-semibold {{ $movement->quantity > 0 ? 'text-success' : 'text-danger' }}">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td><td>{{ $movement->quantity_before }} → {{ $movement->quantity_after }}</td><td>{{ $movement->creator?->name ?: 'System' }}</td><td>{{ $movement->created_at->format('d M H:i') }}</td></tr>
        @empty<tr><td colspan="6" class="text-center text-secondary py-4">No stock movements yet.</td></tr>@endforelse
    </tbody></table></div>@if($movements->hasPages())<div class="card-footer bg-white">{{ $movements->links() }}</div>@endif</div></div>
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

