@extends('layouts.app')

@section('title', 'Reply templates')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1">Reply templates</h1><p class="text-muted mb-0">Reusable text for staff-reviewed Messenger and Telegram replies.</p></div>
    <a href="{{ route('social.inbox.index') }}" class="btn btn-outline-secondary">Back to inbox</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <form method="POST" action="{{ route('social.reply-templates.store') }}" class="card shadow-sm">
            @csrf
            <div class="card-header bg-white fw-semibold">New template</div>
            <div class="card-body vstack gap-3">
                <div><label class="form-label" for="title">Title</label><input class="form-control" id="title" name="title" value="{{ old('title') }}" maxlength="191" required></div>
                <div><label class="form-label" for="body">Reply text</label><textarea class="form-control" id="body" name="body" rows="6" maxlength="2000" required>{{ old('body') }}</textarea></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="active" name="active" value="1" checked><label class="form-check-label" for="active">Available to staff</label></div>
            </div>
            <div class="card-footer bg-white"><button class="btn btn-primary w-100">Create template</button></div>
        </form>
    </div>
    <div class="col-lg-8">
        <div class="vstack gap-3">
            @forelse ($templates as $template)
                <form method="POST" action="{{ route('social.reply-templates.update', $template) }}" class="card shadow-sm">
                    @csrf @method('PUT')
                    <div class="card-body row g-3">
                        <div class="col-md-5"><label class="form-label">Title</label><input class="form-control" name="title" value="{{ $template->title }}" maxlength="191" required></div>
                        <div class="col-md-7"><label class="form-label">Reply text</label><textarea class="form-control" name="body" rows="3" maxlength="2000" required>{{ $template->body }}</textarea></div>
                        <div class="col-12 d-flex justify-content-between align-items-center gap-3">
                            <div><div class="form-check"><input class="form-check-input" type="checkbox" id="active-{{ $template->id }}" name="active" value="1" @checked($template->active)><label class="form-check-label" for="active-{{ $template->id }}">Available to staff</label></div>@if ($template->updater)<small class="text-muted">Last updated by {{ $template->updater->name }}</small>@endif</div>
                            <button class="btn btn-outline-primary">Save template</button>
                        </div>
                    </div>
                </form>
            @empty
                <div class="card card-body text-center text-muted">No reply templates yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
