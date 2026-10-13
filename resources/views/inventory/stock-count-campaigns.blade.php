@extends('layouts.app')
@section('title', 'Count campaigns · SellAssist KH')
@section('content')
<div class="mb-4"><a href="{{ route('inventory.stock-count-tasks.index') }}">← Planned counts</a><h1 class="h3 mt-2 mb-1">Count campaigns</h1><p class="text-secondary mb-0">Assign a batch of different items and review its progress.</p></div>

<div class="row g-4">
    <div class="col-xl-5"><div class="card"><div class="card-body">
        <h2 class="h5">New campaign</h2>
        <form method="POST" action="{{ route('inventory.stock-count-campaigns.store') }}" x-data="campaignForm(@js($catalog), @js(old('assignments', [['product' => '', 'variant' => '', 'assignee' => '']])))">
            @csrf
            <div class="row g-2 mb-3"><div class="col-sm-8"><label class="form-label" for="campaign-name">Name *</label><input id="campaign-name" class="form-control" name="name" value="{{ old('name') }}" maxlength="150" required placeholder="October shelf check"></div><div class="col-sm-4"><label class="form-label" for="campaign-due">Due</label><input id="campaign-due" class="form-control" type="date" name="due_on" value="{{ old('due_on') }}"></div></div>
            <template x-for="(row, index) in rows" :key="row.key">
                <div class="border rounded p-3 mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-2"><strong x-text="`Item ${index + 1}`"></strong><button class="btn btn-sm btn-outline-danger" type="button" x-show="rows.length > 1" @click="removeRow(row.key)">Remove</button></div>
                    <div class="row g-2">
                        <div class="col-12 col-sm-6"><label class="form-label">Product *</label><select class="form-select" :name="`assignments[${index}][product]`" x-model="row.product" @change="row.variant = ''" required><option value="">Choose product</option><template x-for="product in products" :key="product.uuid"><option :value="product.uuid" x-text="product.name"></option></template></select></div>
                        <div class="col-12 col-sm-6" x-show="variants(row).length" x-cloak><label class="form-label">Variant *</label><select class="form-select" :name="`assignments[${index}][variant]`" x-model="row.variant" :required="variants(row).length > 0"><option value="">Choose variant</option><template x-for="variant in variants(row)" :key="variant.uuid"><option :value="variant.uuid" x-text="variant.name"></option></template></select></div>
                        <div class="col-12"><label class="form-label">Assigned to *</label><select class="form-select" :name="`assignments[${index}][assignee]`" x-model="row.assignee" required><option value="">Choose staff</option>@foreach($assignees as $assignee)<option value="{{ $assignee->uuid }}">{{ $assignee->name }}</option>@endforeach</select></div>
                    </div>
                </div>
            </template>
            <button class="btn btn-outline-secondary btn-sm mb-3" type="button" @click="addRow()" :disabled="rows.length >= 50">Add another item</button>
            <button class="btn btn-primary w-100" type="submit">Create campaign</button>
        </form>
    </div></div></div>
    <div class="col-xl-7"><div class="card"><div class="card-header bg-white"><strong>Campaigns</strong></div>
        <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Campaign</th><th>Due</th><th>Progress</th><th>Created by</th><th></th></tr></thead><tbody>
            @forelse($campaigns as $campaign)
                <tr><td>{{ $campaign->name }}</td><td>{{ $campaign->due_on?->format('d M Y') ?: '—' }}</td><td>{{ $campaign->completed_tasks_count }} / {{ $campaign->tasks_count }}</td><td>{{ $campaign->creator->name }}</td><td><a href="{{ route('inventory.stock-count-campaigns.show', $campaign) }}">View</a></td></tr>
            @empty<tr><td colspan="5" class="text-center text-secondary py-4">No campaigns yet.</td></tr>@endforelse
        </tbody></table></div>
        @if($campaigns->hasPages())<div class="card-footer bg-white">{{ $campaigns->links() }}</div>@endif
    </div></div>
</div>
<script>
document.addEventListener('alpine:init', () => Alpine.data('campaignForm', (products, initialRows) => ({
    products,
    rows: initialRows.map((row, key) => ({ key, product: row.product ?? '', variant: row.variant ?? '', assignee: row.assignee ?? '' })),
    nextKey: initialRows.length,
    variants(row) {
        return this.products.find(product => product.uuid === row.product)?.variants ?? [];
    },
    addRow() {
        if (this.rows.length < 50) this.rows.push({ key: this.nextKey++, product: '', variant: '', assignee: '' });
    },
    removeRow(key) {
        this.rows = this.rows.filter(row => row.key !== key);
    },
})));
</script>
@endsection
