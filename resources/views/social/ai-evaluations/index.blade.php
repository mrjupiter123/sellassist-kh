@extends('layouts.app')

@section('title', 'AI profile evaluations')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1">AI profile evaluations</h1><p class="text-muted mb-0">Manually compare profiles against encrypted synthetic Khmer and English cases.</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('social.ai-profiles.index') }}">Back to profiles</a>
</div>

<div class="alert alert-info">Evaluation runs call the configured OpenAI API and may use billable tokens. They never read customer conversations or production catalog data. A passing run does not activate anything: an administrator must approve the run and then activate the profile separately.</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100"><div class="card-header bg-white fw-semibold">Synthetic dataset</div><div class="list-group list-group-flush">
            @forelse ($cases as $case)<div class="list-group-item d-flex justify-content-between"><span>{{ $case->name }}</span><span class="badge text-bg-secondary">{{ strtoupper($case->locale) }}</span></div>@empty<div class="list-group-item text-muted">No active cases. Run <code>php artisan db:seed --class=AiEvaluationCaseSeeder --force</code>.</div>@endforelse
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm h-100"><div class="card-header bg-white fw-semibold">Run a profile</div><div class="card-body"><p class="small text-muted">Approval threshold: {{ number_format($approvalThreshold * 100, 1) }}%. Runs are idempotent while queued or running.</p><div class="vstack gap-2">
            @forelse ($profiles as $profile)<div class="border rounded p-3 d-flex justify-content-between align-items-center gap-3"><div><strong>{{ $profile->name }} {{ $profile->version }}</strong><div class="small text-muted">{{ $profile->model }} @if ($profile->active) · currently active @endif</div></div><form method="POST" action="{{ route('social.ai-evaluations.runs.store', $profile) }}">@csrf<button class="btn btn-outline-primary btn-sm" @disabled($cases->isEmpty())>Run evaluation</button></form></div>@empty<p class="text-muted mb-0">Create an AI profile first.</p>@endforelse
        </div></div></div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Recent evaluation runs</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Requested</th><th>Profile</th><th>Status</th><th>Score</th><th>Cases</th><th>Tokens</th><th>Results</th><th></th></tr></thead><tbody>
        @forelse ($runs as $run)
            <tr>
                <td><div>{{ $run->created_at->format('d M Y H:i') }}</div><small class="text-muted">{{ $run->requester?->name ?? 'Former user' }}</small></td>
                <td><strong>{{ $run->profile->name }} {{ $run->profile->version }}</strong><div class="small text-muted">{{ $run->profile->model }}</div></td>
                <td><span class="badge text-bg-{{ $run->status->value === 'completed' ? 'success' : ($run->status->value === 'failed' ? 'danger' : 'secondary') }}">{{ $run->status->label() }}</span>@if ($run->error)<div class="small text-danger mt-1">{{ $run->error }}</div>@endif</td>
                <td>{{ $run->score === null ? '—' : number_format((float) $run->score * 100, 1).'%' }}</td>
                <td>{{ $run->passed_cases }}/{{ $run->total_cases }} passed</td>
                <td>{{ number_format($run->total_tokens) }}</td>
                <td><details><summary>{{ $run->results->count() }} details</summary><ul class="small ps-3 mt-2 mb-0">@foreach ($run->results as $result)<li>{{ $result->evaluationCase->name }}: {{ number_format((float) $result->score * 100, 1) }}% @if ($result->differences) ({{ implode(', ', $result->differences) }}) @endif</li>@endforeach</ul></details></td>
                <td>@if ($run->status->value === 'completed' && (float) $run->score >= $approvalThreshold)<form method="POST" action="{{ route('social.ai-evaluations.runs.approve', [$run->profile, $run]) }}">@csrf<button class="btn btn-outline-success btn-sm" @disabled($run->profile->activation_eligible)>Approve profile</button></form>@endif</td>
            </tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-4">No evaluation runs yet.</td></tr>@endforelse
    </tbody></table></div>
</div>
@endsection
