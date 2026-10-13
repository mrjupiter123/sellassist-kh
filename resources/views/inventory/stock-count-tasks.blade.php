@extends('layouts.app')
@section('title', 'Planned stock counts · SellAssist KH')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><a href="{{ route('inventory.stock-counts.index') }}">← Physical counts</a><h1 class="h3 mt-2 mb-1">Planned stock counts</h1><p class="text-secondary mb-0">Assign items to staff and track which counts remain open.</p><a class="btn btn-sm btn-outline-secondary mt-2" href="{{ route('inventory.stock-count-campaigns.index') }}">Count campaigns</a></div>
    <div><span class="badge text-bg-warning">{{ $openCount }} open</span> <span class="badge text-bg-success">{{ $completedCount }} completed</span></div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h2 class="h5">Assign a count</h2>
            <form method="POST" action="{{ route('inventory.stock-count-tasks.store') }}" x-data="countTaskForm(@js($catalog))">
                @csrf
                <div class="mb-3"><label class="form-label" for="task-product">Product *</label><select id="task-product" class="form-select" name="product" x-model="productUuid" @change="variantUuid = ''" required><option value="">Choose a product</option><template x-for="product in products" :key="product.uuid"><option :value="product.uuid" x-text="product.name"></option></template></select></div>
                <div class="mb-3" x-show="variants.length" x-cloak><label class="form-label" for="task-variant">Variant *</label><select id="task-variant" class="form-select" name="variant" x-model="variantUuid" :required="variants.length > 0"><option value="">Choose a variant</option><template x-for="variant in variants" :key="variant.uuid"><option :value="variant.uuid" x-text="variant.name"></option></template></select></div>
                <div class="mb-3"><label class="form-label" for="task-assignee">Assigned to *</label><select id="task-assignee" class="form-select" name="assignee" required><option value="">Choose a staff member</option>@foreach($assignees as $assignee)<option value="{{ $assignee->uuid }}" @selected(old('assignee') === $assignee->uuid)>{{ $assignee->name }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label" for="task-due">Due date</label><input id="task-due" class="form-control" type="date" name="due_on" value="{{ old('due_on') }}"></div>
                <div class="mb-3"><label class="form-label" for="task-instructions">Instructions</label><textarea id="task-instructions" class="form-control" name="instructions" rows="2" maxlength="2000">{{ old('instructions') }}</textarea></div>
                <button class="btn btn-primary w-100" type="submit">Assign count</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card"><div class="card-header bg-white"><strong>Count tasks</strong></div>
            <div class="table-responsive"><table class="table table-hover mb-0">
                <thead><tr><th>Item</th><th>Assigned to</th><th>Due</th><th>Status</th><th></th></tr></thead>
                <tbody>@forelse($tasks as $task)
                    <tr><td>{{ $task->product->name }}@if($task->variant)<div class="small text-secondary">{{ $task->variant->display_name }}</div>@endif</td><td>{{ $task->assignee->name }}</td><td>{{ $task->due_on?->format('d M Y') ?: '—' }}</td><td>@if($task->stock_count_id)<span class="badge text-bg-success">Completed</span>@else<span class="badge text-bg-warning">Open</span>@endif</td><td><a href="{{ route('inventory.stock-count-tasks.show', $task) }}">View</a></td></tr>
                @empty<tr><td colspan="5" class="text-center text-secondary py-4">No count tasks yet.</td></tr>@endforelse</tbody>
            </table></div>
            @if($tasks->hasPages())<div class="card-footer bg-white">{{ $tasks->links() }}</div>@endif
        </div>
    </div>
</div>
<script>
document.addEventListener('alpine:init', () => Alpine.data('countTaskForm', (products) => ({
    products,
    productUuid: @js((string) old('product', '')),
    variantUuid: @js((string) old('variant', '')),
    get variants() {
        return this.products.find(product => product.uuid === this.productUuid)?.variants ?? [];
    },
})));
</script>
@endsection
