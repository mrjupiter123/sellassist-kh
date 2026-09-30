@extends('layouts.app')

@section('title', 'Social channels')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Messenger channels</h1>
        <p class="text-muted mb-0">Connect Facebook Page IDs to the secure Meta webhook.</p>
    </div>
    <a href="{{ route('social.inbox.index') }}" class="btn btn-outline-secondary">Back to inbox</a>
</div>

<div class="alert alert-info">
    Meta callback URL: <code>{{ route('api.social.meta.webhook.receive') }}</code>.
    Use the same verification token configured as <code>META_WEBHOOK_VERIFY_TOKEN</code> on the server.
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Add Facebook Page</h2>
                <form method="POST" action="{{ route('social.channels.store') }}" class="vstack gap-3">
                    @csrf
                    <div><label class="form-label" for="name">Display name</label><input class="form-control" id="name" name="name" value="{{ old('name') }}" required></div>
                    <div><label class="form-label" for="external_id">Facebook Page ID</label><input class="form-control" id="external_id" name="external_id" value="{{ old('external_id') }}" required></div>
                    <div class="form-check"><input type="checkbox" class="form-check-input" id="active" name="active" value="1" checked><label class="form-check-label" for="active">Accept incoming messages</label></div>
                    <button class="btn btn-primary">Add channel</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="vstack gap-3">
            @forelse ($channels as $channel)
                <form method="POST" action="{{ route('social.channels.update', $channel) }}" class="card shadow-sm">
                    @csrf @method('PUT')
                    <div class="card-body row g-3 align-items-end">
                        <div class="col-md-5"><label class="form-label">Display name</label><input class="form-control" name="name" value="{{ $channel->name }}" required></div>
                        <div class="col-md-5"><label class="form-label">Facebook Page ID</label><input class="form-control" name="external_id" value="{{ $channel->external_id }}" required></div>
                        <div class="col-md-2"><button class="btn btn-outline-primary w-100">Save</button></div>
                        <div class="col-12 d-flex justify-content-between">
                            <div class="form-check"><input type="checkbox" class="form-check-input" id="active-{{ $channel->id }}" name="active" value="1" @checked($channel->active)><label class="form-check-label" for="active-{{ $channel->id }}">Active</label></div>
                            <small class="text-muted">{{ $channel->contacts_count }} contacts · {{ $channel->conversations_count }} conversations</small>
                        </div>
                    </div>
                </form>
            @empty
                <div class="card card-body text-muted">No Messenger channels configured yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
