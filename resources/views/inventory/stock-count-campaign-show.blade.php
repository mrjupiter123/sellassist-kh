@extends('layouts.app')
@section('title', $campaign->name.' · SellAssist KH')
@section('content')
<div class="mb-4"><a href="{{ route('inventory.stock-count-campaigns.index') }}">← Count campaigns</a><h1 class="h3 mt-2 mb-1">{{ $campaign->name }}</h1><p class="text-secondary mb-0">Created by {{ $campaign->creator->name }} · Due {{ $campaign->due_on?->format('d M Y') ?: 'not set' }}</p></div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-secondary small">Total items</div><div class="h4 mb-0">{{ $progress['total'] }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-secondary small">Completed</div><div class="h4 mb-0">{{ $progress['completed'] }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-secondary small">Open</div><div class="h4 mb-0">{{ $progress['open'] }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card"><div class="card-body"><div class="text-secondary small">Discrepancies</div><div class="h4 mb-0">{{ $progress['discrepancies'] }}</div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-8"><div class="card"><div class="card-header bg-white"><strong>Assignments</strong></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Item</th><th>Assigned to</th><th>Status</th><th>Difference</th><th></th></tr></thead><tbody>
        @foreach($progress['tasks'] as $task)<tr><td>{{ $task->product->name }}@if($task->variant)<div class="small text-secondary">{{ $task->variant->display_name }}</div>@endif</td><td>{{ $task->assignee->name }}</td><td>{{ $task->stock_count_id ? 'Completed' : 'Open' }}</td><td>@if($task->stockCount){{ $task->stockCount->difference > 0 ? '+' : '' }}{{ $task->stockCount->difference }}@else—@endif</td><td><a href="{{ route('inventory.stock-count-tasks.show', $task) }}">View</a></td></tr>@endforeach
    </tbody></table></div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-header bg-white"><strong>By staff member</strong></div><ul class="list-group list-group-flush">
        @foreach($progress['byAssignee'] as $summary)<li class="list-group-item d-flex justify-content-between gap-2"><span>{{ $summary['name'] }}</span><span>{{ $summary['completed'] }} / {{ $summary['total'] }}</span></li>@endforeach
    </ul></div></div>
</div>
@endsection
