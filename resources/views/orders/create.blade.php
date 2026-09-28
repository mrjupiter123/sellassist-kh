@extends('layouts.app')
@php($editing = $order !== null)
@section('title', ($editing ? 'Amend order' : 'New order').' · SellAssist KH')
@section('content')
<div class="mb-4"><a href="{{ $editing ? route('orders.show', $order) : route('orders.index') }}">← {{ $editing ? $order->order_number : 'Orders' }}</a><h1 class="h3 mt-2 mb-1">{{ $editing ? 'Amend order' : 'New order' }}</h1><p class="text-secondary mb-0">Prices and totals are verified again by the server when you save.</p></div>
<form method="POST" action="{{ $editing ? route('orders.update', $order) : route('orders.store') }}" x-data="orderForm">@csrf @if($editing) @method('PUT') @endif
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card mb-4"><div class="card-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Customer *</label><select class="form-select" name="customer_id" required><option value="">Choose a customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) old('customer_id', $order?->customer_id) === (string) $customer->id)>{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>@endforeach</select><div class="form-text"><a href="{{ route('customers.create') }}">Add a new customer</a> if needed.</div></div>
            <div class="col-md-3"><label class="form-label">Source *</label><select class="form-select" name="source" required>@foreach($sources as $source)<option value="{{ $source->value }}" @selected(old('source', $order?->source->value ?? 'manual') === $source->value)>{{ $source->label() }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Currency *</label><select class="form-select" name="currency" x-model="currency" required>@foreach($currencies as $currency)<option value="{{ $currency->value }}">{{ $currency->value }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Status *</label><select class="form-select" name="status"><option value="new" @selected(old('status', $order?->status->value ?? 'new') === 'new')>New</option><option value="draft" @selected(old('status', $order?->status->value) === 'draft')>Draft</option></select></div>
            <div class="col-md-8"><label class="form-label">Notes</label><input class="form-control" name="notes" value="{{ old('notes', $order?->notes) }}" placeholder="Delivery instructions or customer request"></div>
        </div></div></div>

        <div class="card"><div class="card-header bg-white d-flex justify-content-between align-items-center"><strong>Order items</strong><button class="btn btn-sm btn-outline-primary" type="button" @click="addItem">Add product</button></div>
            <div class="table-responsive"><table class="table mb-0 mobile-stack"><thead><tr><th style="min-width: 210px">Product</th><th style="min-width: 160px">Variant</th><th>Price</th><th style="width: 100px">Qty</th><th style="width: 130px">Discount</th><th class="text-end">Line total</th><th></th></tr></thead><tbody>
                <template x-for="(item, index) in items" :key="item.key"><tr>
                    <td><select class="form-select" x-model="item.product_id" :name="`items[${index}][product_id]`" @change="item.product_variant_id = ''" required><option value="">Choose</option><template x-for="product in catalog" :key="product.id"><option :value="product.id" x-text="product.name"></option></template></select><small class="text-secondary" x-text="selectedProduct(item)?.sku || ''"></small></td>
                    <td><select class="form-select" x-model="item.product_variant_id" :name="`items[${index}][product_variant_id]`" :required="variants(item).length > 0" :disabled="variants(item).length === 0"><option value="">No variant</option><template x-for="variant in variants(item)" :key="variant.id"><option :value="variant.id" x-text="`${variant.name} · ${variant.stock} in stock`"></option></template></select></td>
                    <td><span x-text="money(price(item))"></span><div class="small text-secondary" x-text="`${stock(item)} in stock`"></div></td>
                    <td><input class="form-control" type="number" min="1" x-model.number="item.quantity" :name="`items[${index}][quantity]`" required></td>
                    <td><input class="form-control" type="number" step="0.01" min="0" x-model="item.discount" :name="`items[${index}][discount]`"></td>
                    <td class="text-end fw-semibold" x-text="money(lineTotal(item))"></td>
                    <td><button class="btn btn-sm btn-outline-danger" type="button" @click="removeItem(index)" :disabled="items.length === 1" aria-label="Remove item">×</button></td>
                </tr></template>
            </tbody></table></div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-4"><div class="card-body"><h2 class="h5">Order totals</h2>
            <div class="mb-3"><label class="form-label">Order discount</label><input class="form-control" type="number" step="0.01" min="0" name="discount" x-model="orderDiscount"></div>
            <div class="mb-3"><label class="form-label">Delivery fee</label><input class="form-control" type="number" step="0.01" min="0" name="delivery_fee" x-model="deliveryFee"></div>
            <div class="d-flex justify-content-between border-top pt-3"><span>Subtotal</span><strong x-text="money(subtotal)"></strong></div>
            <div class="d-flex justify-content-between fs-5 mt-2"><span>Total estimate</span><strong x-text="money(total)"></strong></div>
        </div></div>
        @unless($editing) @can('payments.create')<div class="card mb-4"><div class="card-body"><h2 class="h5">Optional first payment</h2><div class="mb-3"><label class="form-label">Amount</label><input class="form-control" type="number" step="0.01" min="0" name="payment_amount" value="{{ old('payment_amount') }}"></div><div class="mb-3"><label class="form-label">Method</label><select class="form-select" name="payment_method"><option value="">Choose method</option>@foreach($paymentMethods as $method)<option value="{{ $method->value }}" @selected(old('payment_method') === $method->value)>{{ $method->label() }}</option>@endforeach</select></div><div><label class="form-label">Reference</label><input class="form-control" name="payment_reference" value="{{ old('payment_reference') }}"></div></div></div>@endcan @endunless
        <button class="btn btn-primary btn-lg w-100">{{ $editing ? 'Save amendments' : 'Create order' }}</button>
    </div>
</div>
</form>
<script>
document.addEventListener('alpine:init', () => Alpine.data('orderForm', () => ({
    catalog: @js($catalog),
    items: @js(old('items', $initialItems)).map((item, index) => ({ ...item, key: `${Date.now()}-${index}` })),
    currency: @js(old('currency', $order?->currency->value ?? 'USD')),
    orderDiscount: @js(old('discount', $order?->discount ?? '0')),
    deliveryFee: @js(old('delivery_fee', $order?->delivery_fee ?? '0')),
    addItem() { this.items.push({ key: `${Date.now()}-${Math.random()}`, product_id: '', product_variant_id: '', quantity: 1, discount: '0' }); },
    removeItem(index) { if (this.items.length > 1) this.items.splice(index, 1); },
    selectedProduct(item) { return this.catalog.find(product => Number(product.id) === Number(item.product_id)); },
    variants(item) { return this.selectedProduct(item)?.variants ?? []; },
    selectedVariant(item) { return this.variants(item).find(variant => Number(variant.id) === Number(item.product_variant_id)); },
    price(item) { return Number(this.selectedVariant(item)?.price ?? this.selectedProduct(item)?.price ?? 0); },
    stock(item) { return this.selectedVariant(item)?.stock ?? this.selectedProduct(item)?.stock ?? 0; },
    lineTotal(item) { return Math.max(0, (this.price(item) * Number(item.quantity || 0)) - Number(item.discount || 0)); },
    get subtotal() { return this.items.reduce((sum, item) => sum + this.lineTotal(item), 0); },
    get total() { return Math.max(0, this.subtotal - Number(this.orderDiscount || 0) + Number(this.deliveryFee || 0)); },
    money(value) { return `${this.currency} ${Number(value || 0).toFixed(2)}`; },
})));
</script>
@endsection

