@extends('layouts.app')

@section('title', 'AI extraction profiles')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1">AI extraction profiles</h1><p class="text-muted mb-0">Create immutable prompt/model versions, compare outcomes, and activate one version at a time.</p></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-primary" href="{{ route('social.ai-evaluations.index') }}">Run evaluations</a><a class="btn btn-outline-secondary" href="{{ route('operations.index') }}">View quality dashboard</a></div>
</div>

<div class="alert alert-info">New profiles affect only future extraction requests. Existing and queued extractions preserve their original profile, model, prompt version, and instructions hash. Every activation and rollback requires a reason and is retained in release history.</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Profile versions</div>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Profile</th><th>Model</th><th>Results</th><th>Confidence</th><th>Tokens</th><th>Created</th><th></th></tr></thead><tbody>
                @forelse ($profiles as $profile)
                    <tr>
                        <td><div class="fw-semibold">{{ $profile->name }} <span class="text-muted">{{ $profile->version }}</span></div>@if ($profile->active)<span class="badge text-bg-success">Active</span>@elseif ($profile->activation_eligible)<span class="badge text-bg-primary">Approved</span>@else<span class="badge text-bg-secondary">Evaluation required</span>@endif<details class="small mt-2"><summary>Additional guidance</summary><pre class="text-wrap bg-light rounded p-2 mt-2 mb-0">{{ $profile->instructions }}</pre></details></td>
                        <td><code>{{ $profile->model }}</code></td>
                        <td><div>{{ $profile->extractions_count }} total</div><small class="text-muted">{{ $profile->ready_extractions_count }} ready · {{ $profile->failed_extractions_count }} failed</small></td>
                        <td>{{ $profile->average_confidence === null ? '—' : number_format((float) $profile->average_confidence * 100, 1).'%' }}</td>
                        <td>{{ number_format($profile->total_tokens ?? 0) }}</td>
                        <td><div>{{ $profile->created_at->format('d M Y') }}</div><small class="text-muted">{{ $profile->creator?->name ?? 'Former user' }}</small></td>
                        <td style="min-width:260px">@if ($profile->active)<small class="text-muted">Active since {{ $profile->activated_at?->diffForHumans() }}</small>@elseif ($profile->activation_eligible)<form method="POST" action="{{ route('social.ai-profiles.activate', $profile) }}">@csrf<div class="input-group input-group-sm"><input class="form-control" name="reason" required maxlength="2000" placeholder="Release or rollback reason"><button class="btn btn-outline-primary">{{ $profile->releases_count > 0 ? 'Roll back' : 'Activate' }}</button></div></form>@else<a class="btn btn-outline-secondary btn-sm" href="{{ route('social.ai-evaluations.index') }}">Evaluate first</a>@endif</td>
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
                <div class="alert alert-secondary py-2 mb-0 small">New profiles cannot be activated until a qualifying synthetic evaluation run is explicitly approved.</div>
            </div>
            <div class="card-footer bg-white text-end"><button class="btn btn-primary">Create version</button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold">Profile release history</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Released</th><th>Type</th><th>Profile</th><th>Previous</th><th>Approved score</th><th>Reason</th><th>Administrator</th></tr></thead><tbody>
        @forelse ($releases as $release)
            <tr>
                <td>{{ $release->released_at->format('d M Y H:i') }}</td>
                <td><span class="badge text-bg-{{ $release->type->value === 'rollback' ? 'warning' : 'primary' }}">{{ $release->type->label() }}</span></td>
                <td><strong>{{ $release->profile->name }} {{ $release->profile->version }}</strong><div class="small text-muted">{{ $release->profile->model }}</div></td>
                <td>{{ $release->previousProfile ? $release->previousProfile->name.' '.$release->previousProfile->version : 'Built-in environment profile' }}</td>
                <td>{{ $release->approvalRun?->score === null ? 'Legacy approval' : number_format((float) $release->approvalRun->score * 100, 1).'%' }}</td>
                <td class="text-wrap" style="min-width:220px">{{ $release->reason }}</td>
                <td>{{ $release->releaser?->name ?? 'Former user' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No managed profile releases yet.</td></tr>
        @endforelse
    </tbody></table></div>
</div>
@endsection
