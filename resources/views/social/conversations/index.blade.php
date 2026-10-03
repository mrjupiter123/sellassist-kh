@extends('layouts.app')

@section('title', 'Social inbox')

@section('content')
<div class="d-flex justify-content-between align-items-center gap-3 mb-4">
    <div><h1 class="h3 mb-1">Social order inbox</h1><p class="text-muted mb-0">Review Messenger and Telegram conversations before creating draft orders.</p></div>
    @can('social.channels.manage')<a href="{{ route('social.channels.index') }}" class="btn btn-outline-primary">Social settings</a>@endcan
</div>

<form method="GET" class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-md-7"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search contact name or platform ID"></div>
        <div class="col-md-3"><select class="form-select" name="status"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="list-group list-group-flush">
        @forelse ($conversations as $conversation)
            <a class="list-group-item list-group-item-action py-3" href="{{ route('social.inbox.show', $conversation) }}">
                <div class="d-flex justify-content-between gap-3">
                    <div>
                        <div class="fw-semibold">{{ $conversation->contact->display_name ?: $conversation->channel->platform->label().' user '.$conversation->contact->external_id }}</div>
                        <small class="text-muted">{{ $conversation->channel->platform->label() }} · {{ $conversation->channel->name }} · {{ $conversation->messages_count }} messages</small>
                        @if ($conversation->contact->customer)<div class="small text-success">Linked: {{ $conversation->contact->customer->name }}</div>
                        @elseif ($conversation->contact->suggestedCustomer)<div class="small text-warning">Possible match: {{ $conversation->contact->suggestedCustomer->name }} (review required)</div>@endif
                    </div>
                    <div class="text-end"><span class="badge text-bg-secondary">{{ $conversation->status->label() }}</span><div class="small text-muted mt-1">{{ $conversation->last_message_at?->diffForHumans() }}</div></div>
                </div>
            </a>
        @empty
            <div class="p-5 text-center text-muted">No conversations received yet.</div>
        @endforelse
    </div>
</div>
<div class="mt-3">{{ $conversations->links() }}</div>
@endsection
