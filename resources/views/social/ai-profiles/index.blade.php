@extends('layouts.app')

@section('title', 'AI extraction profiles')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1">AI extraction profiles</h1><p class="text-muted mb-0">Create immutable prompt/model versions, compare outcomes, and activate one version at a time.</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('operations.index') }}">View quality dashboard</a>
</div>

<div class="alert alert-info">New profiles affect only future extraction requests. Existing and queued extractions preserve their original profile, model, prompt version, and instructions hash. Reactivating an older profile is the safe rollback mechanism.</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Profile versions</div>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Profile</th><th>Model</th><th>Results</th><th>Confidence</th><th>Tokens</th><th>Created</th><th></th></tr></thead><tbody>
                @forelse ($profiles as $profile)
                    <tr>
                        <td><div class="fw-semibold">{{ $profile->name }} <span class="text-muted">{{ $profile->version }}</span></div>@if ($profile->active)<span class="badge text-bg-success">Active</span>@endif<details class="small mt-2"><summary>Additional guidance</summary><pre class="text-wrap bg-light rounded p-2 mt-2 mb-0">{{ $profile->instructions }}</pre></details></td>
                        <td><code>{{ $profile->model }}</code></td>
                        <td><div>{{ $profile->extractions_count }} total</div><small class="text-muted">{{ $profile->ready_extractions_count }} ready · {{ $profile->failed_extractions_count }} failed</small></td>
                        <td>{{ $profile->average_confidence === null ? '—' : number_format((float) $profile->average_confidence * 100, 1).'%' }}</td>
                        <td>{{ number_format($profile->total_tokens ?? 0) }}</td>
                        <td><div>{{ $profile->created_at->format('d M Y') }}</div><small class="text-muted">{{ $profile->creator?->name ?? 'Former user' }}</small></td>
                        <td>@if (! $profile->active)<form method="POST" action="{{ route('social.ai-profiles.activate', $profile) }}">@csrf<button class="btn btn-outline-primary btn-sm">Activate</button></form>@else<small class="text-muted">{{ $profile->activated_at?->diffForHumans() }}</small>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No managed profiles yet. Until one is activated, the environment model and built-in prompt are used.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </div>
    <div class="col-xl-4">
        <form method="POST" action="{{ route('social.ai-profiles.store') }}" class="card shadow-sm">
            @csrf
            <div class="card-header bg-white fw-semibold">Create immutable version</div>
            <div class="card-body vstack gap-3">
                <div><label class="form-label" for="profile-name">Profile name</label><input class="form-control" id="profile-name" name="name" maxlength="100" value="{{ old('name', 'Social order extraction') }}" required></div>
                <div><label class="form-label" for="profile-version">Version</label><input class="form-control" id="profile-version" name="version" maxlength="50" value="{{ old('version') }}" placeholder="v2-kh-addresses" required><div class="form-text">Letters, numbers, dots, underscores, and hyphens only.</div></div>
                <div><label class="form-label" for="profile-model">OpenAI model</label><input class="form-control" id="profile-model" name="model" maxlength="100" value="{{ old('model', config('social.ai.model')) }}" required><div class="form-text">Activation does not verify account access. Test a new version before broad use.</div></div>
                <div><label class="form-label" for="profile-instructions">Additional extraction guidance</label><textarea class="form-control" id="profile-instructions" name="instructions" rows="8" maxlength="10000" required>{{ old('instructions') }}</textarea><div class="form-text">The permanent safety and seller-review instructions are always prepended and cannot be removed.</div></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="profile-activate" name="activate" value="1" @checked(old('activate'))><label class="form-check-label" for="profile-activate">Activate immediately</label></div>
            </div>
            <div class="card-footer bg-white text-end"><button class="btn btn-primary">Create version</button></div>
        </form>
    </div>
</div>
@endsection
