@extends('layouts.app')
@section('title', 'Physical stock counts · SellAssist KH')
@section('content')
<div class="mb-4">
    <a href="{{ route('inventory.index') }}">← Inventory</a>
    <h1 class="h3 mt-2 mb-1">Physical stock counts</h1>
    <p class="text-secondary mb-0">Enter what you counted. The server checks current stock before recording any difference.</p>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h2 class="h5">Record a count</h2>
            <form method="POST" action="{{ route('inventory.stock-counts.store') }}" x-data="stockCountForm(@js($catalog))">
                @csrf
                <input type="hidden" name="uuid" value="{{ old('uuid', $submissionToken) }}">
                <div class="mb-3">
                    <label class="form-label" for="count-product">Product *</label>
                    <select id="count-product" class="form-select" name="product_id" x-model="productId" @change="variantId = ''" required>
                        <option value="">Choose a product</option>
                        <template x-for="product in products" :key="product.id"><option :value="product.id" x-text="product.name"></option></template>
                    </select>
                </div>
                <div class="mb-3" x-show="variants.length" x-cloak>
                    <label class="form-label" for="count-variant">Variant *</label>
                    <select id="count-variant" class="form-select" name="product_variant_id" x-model="variantId" :required="variants.length > 0">
                        <option value="">Choose a variant</option>
                        <template x-for="variant in variants" :key="variant.id"><option :value="variant.id" x-text="variant.name"></option></template>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Stock when page opened</label>
                    <div class="form-control bg-light" x-text="currentStock === '' ? 'Choose an item' : currentStock"></div>
                    <input type="hidden" name="observed_quantity" :value="currentStock">
                    <div class="form-text">If stock changes before you save, refresh and recount.</div>
                </div>
                <div class="mb-3"><label class="form-label" for="count-quantity">Physical quantity counted *</label><input id="count-quantity" class="form-control" type="number" min="0" max="100000000" name="counted_quantity" value="{{ old('counted_quantity') }}" required></div>
                <div class="mb-3"><label class="form-label" for="count-notes">Notes</label><textarea id="count-notes" class="form-control" name="notes" rows="2">{{ old('notes') }}</textarea></div>
                <button class="btn btn-primary w-100" type="submit">Record physical count</button>
            </form>
        </div></div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white"><strong>Recent counts</strong></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Item</th><th class="text-end">Expected</th><th class="text-end">Counted</th><th class="text-end">Difference</th><th>By</th><th>Date</th></tr></thead>
                    <tbody>
                        @forelse($counts as $count)
                            <tr>
                                <td>@can('products.view')<a href="{{ route('products.show', $count->product) }}">{{ $count->product->name }}</a>@else{{ $count->product->name }}@endcan<div class="small text-secondary">{{ $count->variant?->display_name }}</div>@if($count->notes)<div class="small text-secondary">{{ $count->notes }}</div>@endif</td>
                                <td class="text-end">{{ $count->expected_quantity }}</td>
                                <td class="text-end">{{ $count->counted_quantity }}</td>
                                <td class="text-end {{ $count->difference < 0 ? 'text-danger' : ($count->difference > 0 ? 'text-success' : '') }}">{{ $count->difference > 0 ? '+' : '' }}{{ $count->difference }}</td>
                                <td>{{ $count->creator->name }}</td>
                                <td>{{ $count->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">No physical counts recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($counts->hasPages())<div class="card-footer bg-white">{{ $counts->links() }}</div>@endif
        </div>
    </div>
</div>
<script>
document.addEventListener('alpine:init', () => Alpine.data('stockCountForm', (products) => ({
    products,
    productId: @js((string) old('product_id', '')),
    variantId: @js((string) old('product_variant_id', '')),
    get selectedProduct() {
        return this.products.find(product => Number(product.id) === Number(this.productId));
    },
    get variants() {
        return this.selectedProduct?.variants ?? [];
    },
    get currentStock() {
        if (!this.selectedProduct) return '';
        if (!this.variants.length) return this.selectedProduct.stock;
        return this.variants.find(variant => Number(variant.id) === Number(this.variantId))?.stock ?? '';
    },
})));
</script>
@endsection
