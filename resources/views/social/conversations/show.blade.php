@extends('layouts.app')

@section('title', 'Social conversation')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1">{{ $conversation->contact->display_name ?: $conversation->channel->platform->label().' user' }}</h1><p class="text-muted mb-0">{{ $conversation->channel->name }} · {{ $conversation->channel->platform->label() }} ID {{ $conversation->contact->external_id }}</p></div>
    <a href="{{ route('social.inbox.index') }}" class="btn btn-outline-secondary">Back to inbox</a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Conversation</div>
            <div class="card-body vstack gap-3" style="max-height: 560px; overflow-y: auto">
                @forelse ($conversation->messages as $message)
                    <div class="d-flex {{ $message->direction->value === 'outbound' ? 'justify-content-end' : 'justify-content-start' }}">
                        <div class="rounded-3 px-3 py-2 {{ $message->direction->value === 'outbound' ? 'bg-primary text-white' : 'bg-light' }}" style="max-width: 85%">
                            <div>{{ $message->body ?: '['.$message->type->value.']' }}</div>
                            <small class="opacity-75">{{ $message->sent_at->format('d M Y H:i') }}</small>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No messages.</p>
                @endforelse
            </div>
        </div>

        @if ($conversation->convertedOrder)
            <div class="alert alert-success">Converted to <a href="{{ route('orders.show', $conversation->convertedOrder) }}">{{ $conversation->convertedOrder->order_number }}</a>. Inventory was not deducted because it is a draft.</div>
        @elseif ($conversation->contact->customer)
            <div class="card shadow-sm" x-data="{ items: [{ product_id: '', product_variant_id: '', quantity: 1, discount: '0' }], catalog: {{ Illuminate\Support\Js::from($catalog) }}, product(row) { return this.catalog.find(p => String(p.id) === String(row.product_id)); }, add() { this.items.push({ product_id: '', product_variant_id: '', quantity: 1, discount: '0' }); } }">
                <div class="card-header bg-white"><strong>Create reviewed draft order</strong><div class="small text-muted">Prices are resolved from the product catalog on the server.</div></div>
                <form method="POST" action="{{ route('social.inbox.draft-order.store', $conversation) }}" class="card-body">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4"><label class="form-label">Currency</label><select class="form-select" name="currency">@foreach ($currencies as $currency)<option value="{{ $currency->value }}">{{ $currency->label() }}</option>@endforeach</select></div>
                        <div class="col-sm-4"><label class="form-label">Order discount</label><input class="form-control" type="number" min="0" step="0.01" name="discount" value="0"></div>
                        <div class="col-sm-4"><label class="form-label">Delivery fee</label><input class="form-control" type="number" min="0" step="0.01" name="delivery_fee" value="0"></div>
                    </div>
                    <template x-for="(row, index) in items" :key="index">
                        <div class="row g-2 align-items-end border-top py-3">
                            <div class="col-md-4"><label class="form-label">Product</label><select class="form-select" :name="`items[${index}][product_id]`" x-model="row.product_id" @change="row.product_variant_id = ''" required><option value="">Choose product</option><template x-for="product in catalog" :key="product.id"><option :value="product.id" x-text="`${product.name} (${product.price})`"></option></template></select></div>
                            <div class="col-md-3"><label class="form-label">Variant</label><select class="form-select" :name="`items[${index}][product_variant_id]`" x-model="row.product_variant_id"><option value="">No variant</option><template x-for="variant in (product(row)?.variants || [])" :key="variant.id"><option :value="variant.id" x-text="`${variant.name} (${variant.price})`"></option></template></select></div>
                            <div class="col-4 col-md-2"><label class="form-label">Qty</label><input class="form-control" type="number" min="1" :name="`items[${index}][quantity]`" x-model="row.quantity" required></div>
                            <div class="col-5 col-md-2"><label class="form-label">Line discount</label><input class="form-control" type="number" min="0" step="0.01" :name="`items[${index}][discount]`" x-model="row.discount"></div>
                            <div class="col-3 col-md-1"><button type="button" class="btn btn-outline-danger" @click="items.splice(index, 1)" :disabled="items.length === 1">×</button></div>
                        </div>
                    </template>
                    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" @click="add()">Add product</button>
                    <div class="mb-3"><label class="form-label">Seller notes</label><textarea class="form-control" name="notes" rows="3"></textarea></div>
                    <button class="btn btn-primary">Create draft for review</button>
                </form>
            </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Customer identity</div>
            <div class="card-body">
                @if ($conversation->contact->customer)
                    <p class="mb-1">Linked to <a href="{{ route('customers.show', $conversation->contact->customer) }}">{{ $conversation->contact->customer->name }}</a></p>
                    <p class="text-muted mb-0">{{ $conversation->contact->customer->phone ?: 'No phone recorded' }}</p>
                @else
                    @if ($conversation->contact->suggestedCustomer)<div class="alert alert-warning">Possible match: {{ $conversation->contact->suggestedCustomer->name }}. This was not linked automatically.</div>@endif
                    @can('social.manage')
                        <form method="POST" action="{{ route('social.inbox.link-customer', $conversation) }}" class="mb-4">@csrf<label class="form-label">Link existing customer</label><div class="input-group"><select class="form-select" name="customer_id" required><option value="">Choose customer</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }} {{ $customer->phone ? '— '.$customer->phone : '' }}</option>@endforeach</select><button class="btn btn-outline-primary">Link</button></div></form>
                        <hr>
                        <form method="POST" action="{{ route('social.inbox.customers.store', $conversation) }}" class="vstack gap-2">@csrf<h2 class="h6">Create new customer</h2><input class="form-control" name="name" value="{{ $conversation->contact->display_name }}" placeholder="Name / ឈ្មោះ" required><input class="form-control" name="phone" placeholder="Phone / លេខទូរស័ព្ទ"><textarea class="form-control" name="address" placeholder="Address / អាសយដ្ឋាន"></textarea><div class="row g-2"><div class="col"><input class="form-control" name="commune" placeholder="Commune / ឃុំ-សង្កាត់"></div><div class="col"><input class="form-control" name="district" placeholder="District / ស្រុក-ខណ្ឌ"></div></div><input class="form-control" name="province" placeholder="Province / ខេត្ត-រាជធានី"><button class="btn btn-primary">Create and link</button></form>
                    @endcan
                @endif
            </div>
        </div>
        <div class="alert alert-secondary small">Incoming messages never confirm orders, deduct inventory, record payments, or create shipments. A seller must review the draft and use the normal order workflow.</div>
    </div>
</div>
@endsection
