@extends('layouts.app')

@section('title', 'Social conversation')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1">{{ $conversation->contact->display_name ?: $conversation->channel->platform->label().' user' }}</h1><p class="text-muted mb-0">{{ $conversation->channel->name }} · {{ $conversation->channel->platform->label() }} ID {{ $conversation->contact->external_id }}</p></div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('social.inbox.read.destroy', $conversation) }}">@csrf @method('DELETE')<button class="btn btn-outline-primary">Mark unread</button></form>
        <a href="{{ route('social.inbox.index') }}" class="btn btn-outline-secondary">Back to inbox</a>
    </div>
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
                            @if ($message->delivery_status)
                                <span class="badge {{ $message->delivery_status->isFailed() ? 'text-bg-danger' : 'text-bg-light' }} ms-1">{{ $message->delivery_status->label() }}</span>
                                @if ($message->delivery_status->isFailed() && $message->delivery_error)
                                    <div class="small mt-1">{{ $message->delivery_error }}</div>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No messages.</p>
                @endforelse
            </div>
        </div>

        @can('social.reply')
            @if ($conversation->status->allowsReplies())
                <form method="POST" action="{{ route('social.inbox.replies.store', $conversation) }}" class="card shadow-sm mb-4" x-data="{ body: {{ Illuminate\Support\Js::from(old('body', '')) }}, templates: {{ Illuminate\Support\Js::from($replyTemplates) }}, useTemplate(id) { const template = this.templates.find(item => String(item.id) === String(id)); if (template) this.body = template.body; } }">
                    @csrf
                    <div class="card-header bg-white fw-semibold">Reply to customer</div>
                    <div class="card-body">
                        @if ($replyTemplates->isNotEmpty())
                            <label class="form-label" for="reply-template">Reply template</label>
                            <select id="reply-template" class="form-select mb-3" @change="useTemplate($event.target.value)"><option value="">Write a custom reply</option>@foreach ($replyTemplates as $template)<option value="{{ $template->id }}">{{ $template->title }}</option>@endforeach</select>
                        @endif
                        <label class="form-label" for="reply-body">Message</label>
                        <textarea id="reply-body" class="form-control @error('body') is-invalid @enderror" name="body" rows="3" maxlength="2000" x-model="body" required></textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">The reply is encrypted locally and delivered through the integrations queue.</div>
                    </div>
                    <div class="card-footer bg-white text-end"><button class="btn btn-primary">Send reply</button></div>
                </form>
            @else
                <div class="alert alert-secondary">This conversation is archived and cannot receive replies.</div>
            @endif
        @endcan

        @if ($conversation->convertedOrder)
            <div class="alert alert-success">Converted to <a href="{{ route('orders.show', $conversation->convertedOrder) }}">{{ $conversation->convertedOrder->order_number }}</a>. Inventory was not deducted because it is a draft.</div>
        @elseif ($conversation->contact->customer)
            <div class="card shadow-sm" x-data="{ items: {{ Illuminate\Support\Js::from($suggestedOrderItems ?: [['product_id' => '', 'product_variant_id' => '', 'quantity' => 1, 'discount' => '0']]) }}, catalog: {{ Illuminate\Support\Js::from($catalog) }}, product(row) { return this.catalog.find(p => String(p.id) === String(row.product_id)); }, add() { this.items.push({ product_id: '', product_variant_id: '', quantity: 1, discount: '0' }); } }">
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
                    <div class="mb-3"><label class="form-label">Seller notes</label><textarea class="form-control" name="notes" rows="3">{{ old('notes', $suggestedCustomer['notes']) }}</textarea></div>
                    <button class="btn btn-primary">Create draft for review</button>
                </form>
            </div>
        @endif
    </div>

    <div class="col-lg-5">
        @can('social.manage')
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">Inbox workflow</div>
                <div class="card-body vstack gap-3">
                    <form method="POST" action="{{ route('social.inbox.assignment.update', $conversation) }}">
                        @csrf @method('PATCH')
                        <label class="form-label" for="assigned_to">Assigned staff</label>
                        <div class="input-group"><select class="form-select" id="assigned_to" name="assigned_to"><option value="">Unassigned</option>@foreach ($assignees as $assignee)<option value="{{ $assignee->id }}" @selected($conversation->assigned_to === $assignee->id)>{{ $assignee->name }}</option>@endforeach</select><button class="btn btn-outline-primary">Assign</button></div>
                        @if ($conversation->assigned_at)<div class="form-text">Assigned {{ $conversation->assigned_at->diffForHumans() }}{{ $conversation->assigner ? ' by '.$conversation->assigner->name : '' }}.</div>@endif
                    </form>
                    <form method="POST" action="{{ route('social.inbox.status.update', $conversation) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $conversation->status->toggleTarget()->value }}">
                        <button class="btn {{ $conversation->status->isArchived() ? 'btn-outline-success' : 'btn-outline-secondary' }} w-100">{{ $conversation->status->isArchived() ? 'Reopen conversation' : 'Archive conversation' }}</button>
                    </form>
                </div>
            </div>
        @endcan
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2">
                <strong>AI order suggestions</strong>
                @if ($latestExtraction)<span class="badge text-bg-{{ $latestExtraction->status->value === 'ready' ? 'success' : ($latestExtraction->status->value === 'failed' ? 'danger' : 'secondary') }}">{{ $latestExtraction->status->label() }}</span>@endif
            </div>
            <div class="card-body">
                <p class="small text-muted">Only when you click generate, up to 40 inbound customer messages and limited active catalog identifiers are sent to OpenAI. Prices, stock, payments, and credentials are not sent. Results are suggestions only and never create or confirm an order.</p>
                @if (! $aiExtractionConfigured)
                    <div class="alert alert-warning py-2 mb-0">AI suggestions are disabled until the server environment is configured.</div>
                @elseif ($conversation->status->value !== 'open' || $conversation->converted_order_id)
                    <div class="alert alert-secondary py-2 mb-0">Suggestions are only available for open, unconverted conversations.</div>
                @else
                    @can('social.extract')
                        <form method="POST" action="{{ route('social.inbox.order-extractions.store', $conversation) }}" class="mb-3">
                            @csrf
                            <button class="btn btn-outline-primary" @disabled($latestExtraction?->status->isPending())>{{ $latestExtraction ? 'Generate from latest messages' : 'Generate suggestions' }}</button>
                        </form>
                    @endcan
                @endif

                @if ($latestExtraction?->status->isPending())
                    <div class="alert alert-info py-2 mb-0">The request is waiting for the integrations queue. Refresh this page shortly.</div>
                @elseif ($latestExtraction?->status->value === 'failed')
                    <div class="alert alert-danger py-2 mb-0">{{ $latestExtraction->error }}</div>
                @elseif ($latestExtraction?->status->value === 'ready')
                    <div class="small mb-3">Confidence: {{ number_format((float) $latestExtraction->overall_confidence * 100) }}% · {{ $latestExtraction->model }}</div>
                    @if ((float) $latestExtraction->overall_confidence < $aiLowConfidenceThreshold)
                        <div class="alert alert-warning py-2">Low-confidence suggestion. Review every customer and product field carefully.</div>
                    @endif
                    @if ($latestExtraction->total_tokens !== null)
                        <div class="small text-muted mb-3">Usage: {{ number_format($latestExtraction->input_tokens ?? 0) }} input + {{ number_format($latestExtraction->output_tokens ?? 0) }} output = {{ number_format($latestExtraction->total_tokens) }} tokens.</div>
                    @endif
                    @if (collect($suggestedCustomer)->filter()->isNotEmpty())
                        <dl class="row small mb-3">
                            @foreach (['name' => 'Name', 'phone' => 'Phone', 'address' => 'Address', 'commune' => 'Commune', 'district' => 'District', 'province' => 'Province'] as $key => $label)
                                @if ($suggestedCustomer[$key])<dt class="col-4">{{ $label }}</dt><dd class="col-8">{{ $suggestedCustomer[$key] }}</dd>@endif
                            @endforeach
                        </dl>
                    @endif
                    <div class="list-group list-group-flush border rounded">
                        @forelse ($latestExtraction->items as $item)
                            <div class="list-group-item small {{ (float) $item->confidence < $aiLowConfidenceThreshold ? 'list-group-item-warning' : '' }}">
                                <div class="fw-semibold">{{ $item->product?->name ?? $item->product_query }} × {{ $item->quantity }}</div>
                                <div class="text-muted">{{ $item->variant?->display_name ?? $item->variant_query }} · {{ number_format((float) $item->confidence * 100) }}% confidence</div>
                                @if (! $item->product)<div class="text-warning">Catalog match unresolved—select it manually.</div>@endif
                            </div>
                        @empty
                            <div class="list-group-item text-muted small">No product lines were found.</div>
                        @endforelse
                    </div>
                    <div class="form-text mt-2">Matched products are prefilled in the draft form. Review every field before submitting.</div>
                    @can('social.extract')
                        <form method="POST" action="{{ route('social.inbox.order-extractions.review', [$conversation, $latestExtraction]) }}" class="border-top mt-3 pt-3">
                            @csrf @method('PUT')
                            <h2 class="h6">Rate this suggestion</h2>
                            <div class="mb-2">
                                <label class="form-label" for="extraction-verdict">Result</label>
                                <select class="form-select" id="extraction-verdict" name="verdict" required>
                                    <option value="">Choose result</option>
                                    @foreach ($extractionReviewVerdicts as $verdict)<option value="{{ $verdict->value }}" @selected(old('verdict', $latestExtraction->review?->verdict?->value) === $verdict->value)>{{ $verdict->label() }}</option>@endforeach
                                </select>
                            </div>
                            <div class="row g-2 mb-2">
                                @foreach (['customer_fields_correct' => 'Customer fields', 'item_matches_correct' => 'Product matches', 'quantities_correct' => 'Quantities'] as $field => $label)
                                    <div class="col-md-4"><label class="form-label" for="{{ $field }}">{{ $label }}</label><select class="form-select form-select-sm" id="{{ $field }}" name="{{ $field }}"><option value="">Not rated</option><option value="1" @selected(old($field, $latestExtraction->review?->{$field}) === true || old($field) === '1')>Correct</option><option value="0" @selected(old($field, $latestExtraction->review?->{$field}) === false || old($field) === '0')>Needs correction</option></select></div>
                                @endforeach
                            </div>
                            <div class="mb-2"><label class="form-label" for="extraction-notes">Correction notes</label><textarea class="form-control" id="extraction-notes" name="notes" rows="2" maxlength="2000">{{ old('notes', $latestExtraction->review?->notes) }}</textarea><div class="form-text">Notes are encrypted. Do not include payment credentials or secrets.</div></div>
                            <button class="btn btn-outline-secondary btn-sm">Save feedback</button>
                            @if ($latestExtraction->review)<span class="small text-muted ms-2">Last reviewed {{ $latestExtraction->review->reviewed_at->diffForHumans() }}{{ $latestExtraction->review->reviewer ? ' by '.$latestExtraction->review->reviewer->name : '' }}.</span>@endif
                        </form>
                    @endcan
                @endif
            </div>
        </div>
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
                        <form method="POST" action="{{ route('social.inbox.customers.store', $conversation) }}" class="vstack gap-2">@csrf<h2 class="h6">Create new customer</h2><input class="form-control" name="name" value="{{ old('name', $suggestedCustomer['name'] ?: $conversation->contact->display_name) }}" placeholder="Name / ឈ្មោះ" required><input class="form-control" name="phone" value="{{ old('phone', $suggestedCustomer['phone']) }}" placeholder="Phone / លេខទូរស័ព្ទ"><textarea class="form-control" name="address" placeholder="Address / អាសយដ្ឋាន">{{ old('address', $suggestedCustomer['address']) }}</textarea><div class="row g-2"><div class="col"><input class="form-control" name="commune" value="{{ old('commune', $suggestedCustomer['commune']) }}" placeholder="Commune / ឃុំ-សង្កាត់"></div><div class="col"><input class="form-control" name="district" value="{{ old('district', $suggestedCustomer['district']) }}" placeholder="District / ស្រុក-ខណ្ឌ"></div></div><input class="form-control" name="province" value="{{ old('province', $suggestedCustomer['province']) }}" placeholder="Province / ខេត្ត-រាជធានី"><button class="btn btn-primary">Create and link</button></form>
                    @endcan
                @endif
            </div>
        </div>
        <div class="alert alert-secondary small">Incoming messages never confirm orders, deduct inventory, record payments, or create shipments. A seller must review the draft and use the normal order workflow.</div>
    </div>
</div>
@endsection
