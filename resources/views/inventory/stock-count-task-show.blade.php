@extends('layouts.app')
@section('title', 'Assigned stock count · SellAssist KH')
@section('content')
<div class="mb-4"><a href="{{ route('inventory.stock-count-tasks.index') }}">← Planned stock counts</a><h1 class="h3 mt-2 mb-1">Assigned stock count</h1><p class="text-secondary mb-0">{{ $task->product->name }}@if($task->variant) · {{ $task->variant->display_name }}@endif</p></div>

<div class="row g-4">
    <div class="col-lg-5"><div class="card"><div class="card-body">
        <h2 class="h5">Assignment</h2>
        <dl class="row mb-0">
            <dt class="col-sm-5">Assigned to</dt><dd class="col-sm-7">{{ $task->assignee->name }}</dd>
            <dt class="col-sm-5">Created by</dt><dd class="col-sm-7">{{ $task->creator->name }}</dd>
            <dt class="col-sm-5">Due</dt><dd class="col-sm-7">{{ $task->due_on?->format('d M Y') ?: 'No deadline' }}</dd>
            <dt class="col-sm-5">Status</dt><dd class="col-sm-7">{{ $task->stock_count_id ? 'Completed' : 'Open' }}</dd>
            @if($task->campaign)<dt class="col-sm-5">Campaign</dt><dd class="col-sm-7"><a href="{{ route('inventory.stock-count-campaigns.show', $task->campaign) }}">{{ $task->campaign->name }}</a></dd>@endif
            @if($task->instructions)<dt class="col-sm-5">Instructions</dt><dd class="col-sm-7">{{ $task->instructions }}</dd>@endif
        </dl>
        @if($task->stockCount)<a href="{{ route('inventory.stock-counts.show', $task->stockCount) }}">View completed physical count</a>@endif
    </div></div></div>
    <div class="col-lg-7"><div class="card"><div class="card-body">
        @if($task->stock_count_id)
            <h2 class="h5">Completed</h2><p class="mb-0">This assignment has been counted. Any further correction must be a new, audited count.</p>
        @elseif($task->assigned_to === auth()->id() || auth()->user()->hasRole('admin'))
            <h2 class="h5">Record physical count</h2>
            <p class="small text-secondary">Count the item now. If its recorded stock changes before you submit, refresh and recount.</p>
            <form method="POST" action="{{ route('inventory.stock-count-tasks.complete', $task) }}">
                @csrf
                <input type="hidden" name="uuid" value="{{ old('uuid', $submissionToken) }}">
                <input type="hidden" name="observed_quantity" value="{{ $task->variant?->stock_quantity ?? $task->product->stock_quantity }}">
                <div class="mb-3"><label class="form-label">Current recorded stock</label><div class="form-control bg-light">{{ $task->variant?->stock_quantity ?? $task->product->stock_quantity }}</div></div>
                <div class="mb-3"><label class="form-label" for="task-counted">Physical quantity counted *</label><input id="task-counted" class="form-control" type="number" min="0" max="100000000" name="counted_quantity" value="{{ old('counted_quantity') }}" required></div>
                <div class="mb-3"><label class="form-label" for="task-notes">Count notes</label><textarea id="task-notes" class="form-control" name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea></div>
                <button class="btn btn-primary" type="submit">Complete count</button>
            </form>
        @else
            <p class="mb-0">Only the assigned user or an administrator can complete this count.</p>
        @endif
    </div></div></div>
</div>
@endsection
