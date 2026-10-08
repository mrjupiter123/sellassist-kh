@extends('layouts.app')

@section('title', 'AI profile evaluations')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1">AI profile evaluations</h1><p class="text-muted mb-0">Versioned synthetic datasets, profile comparisons, and controlled release approval.</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('social.ai-profiles.index') }}">Back to profiles</a>
</div>

<div class="alert alert-info">Evaluation runs may use billable OpenAI tokens. Cases are synthetic and encrypted; runs never read customer conversations or production catalog data. Dataset versions are frozen, and approval never activates a profile automatically.</div>

<div class="accordion mb-4" id="evaluation-management">
    <div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#create-case">Create synthetic case</button></h2><div id="create-case" class="accordion-collapse collapse" data-bs-parent="#evaluation-management"><div class="accordion-body">
        <form method="POST" action="{{ route('social.ai-evaluations.cases.store') }}" class="row g-3">@csrf
            <div class="col-md-8"><label class="form-label">Case name</label><input class="form-control" name="name" value="{{ old('name') }}" required maxlength="150"></div>
            <div class="col-md-4"><label class="form-label">Locale</label><input class="form-control" name="locale" value="{{ old('locale', 'km') }}" required maxlength="10"></div>
            <div class="col-12"><label class="form-label">Synthetic messages</label><textarea class="form-control" name="messages_text" rows="3" required placeholder="One synthetic message per line">{{ old('messages_text') }}</textarea></div>
            <div class="col-lg-6"><label class="form-label">Synthetic catalog JSON</label><textarea class="form-control font-monospace" name="catalog_json" rows="6" required placeholder='[{"product_ref":"synthetic-uuid","name":"Shirt","variants":[]}]'>{{ old('catalog_json') }}</textarea></div>
            <div class="col-lg-6"><label class="form-label">Expected extraction JSON</label><textarea class="form-control font-monospace" name="expected_result_json" rows="6" required placeholder='{"customer_name":"Test Customer","phone":"012000000","items":[]}'>{{ old('expected_result_json') }}</textarea></div>
            <div class="col-12"><button class="btn btn-outline-primary">Create immutable case</button></div>
        </form>
    </div></div></div>
    <div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#create-dataset">Freeze dataset version</button></h2><div id="create-dataset" class="accordion-collapse collapse" data-bs-parent="#evaluation-management"><div class="accordion-body">
        <form method="POST" action="{{ route('social.ai-evaluations.datasets.store') }}" class="row g-3">@csrf
            <div class="col-md-8"><label class="form-label">Dataset name</label><input class="form-control" name="name" value="Core order extraction" required maxlength="150"></div>
            <div class="col-md-4"><label class="form-label">Version</label><input class="form-control" name="version" required maxlength="40" placeholder="v2"></div>
            <div class="col-12"><label class="form-label">Dataset release notes</label><textarea class="form-control" name="release_notes" rows="2" required maxlength="2000" placeholder="Explain added edge cases and why this version exists."></textarea></div>
            <div class="col-12"><div class="form-label">Cases</div>@forelse ($cases as $case)<div class="form-check"><input class="form-check-input" type="checkbox" name="case_ids[]" value="{{ $case->id }}" id="dataset-case-{{ $case->uuid }}"><label class="form-check-label" for="dataset-case-{{ $case->uuid }}">{{ $case->name }} <span class="badge text-bg-secondary">{{ strtoupper($case->locale) }}</span></label></div>@empty<p class="small text-muted">Create a case first.</p>@endforelse</div>
            <div class="col-12"><button class="btn btn-outline-primary" @disabled($cases->isEmpty())>Create and freeze version</button></div>
        </form>
    </div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100"><div class="card-header bg-white fw-semibold">Frozen dataset versions</div><div class="list-group list-group-flush">
            @forelse ($datasets as $dataset)<div class="list-group-item"><div class="d-flex justify-content-between"><strong>{{ $dataset->name }} {{ $dataset->version }}</strong><span class="badge text-bg-secondary">{{ $dataset->cases_count }} cases</span></div><div class="small text-muted mt-1">{{ $dataset->release_notes }}</div></div>@empty<div class="list-group-item text-muted">No frozen dataset versions.</div>@endforelse
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm h-100"><div class="card-header bg-white fw-semibold">Run a profile</div><div class="card-body"><p class="small text-muted">Approval threshold: {{ number_format($approvalThreshold * 100, 1) }}%. Runs are idempotent while queued or running.</p><div class="vstack gap-2">
            @forelse ($profiles as $profile)<div class="border rounded p-3"><div class="mb-2"><strong>{{ $profile->name }} {{ $profile->version }}</strong><div class="small text-muted">{{ $profile->model }} @if ($profile->active) · currently active @endif</div></div><form method="POST" action="{{ route('social.ai-evaluations.runs.store', $profile) }}">@csrf<div class="input-group input-group-sm"><select class="form-select" name="dataset_id" required><option value="">Choose frozen dataset</option>@foreach ($datasets as $dataset)<option value="{{ $dataset->uuid }}">{{ $dataset->name }} {{ $dataset->version }}</option>@endforeach</select><button class="btn btn-outline-primary" @disabled($datasets->isEmpty())>Run evaluation</button></div></form></div>@empty<p class="text-muted mb-0">Create an AI profile first.</p>@endforelse
        </div></div></div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Side-by-side profile comparison</div>
    <div class="card-body">
        <p class="small text-muted">Runs must use the same frozen dataset. A per-case score drop greater than {{ number_format($regressionTolerance * 100, 1) }}%, or a formerly passing case that fails, is a regression.</p>
        <form method="GET" action="{{ route('social.ai-evaluations.index') }}" class="row g-2 mb-3">
            <div class="col-md-5"><select class="form-select" name="baseline" required><option value="">Baseline run</option>@foreach ($completedRuns as $run)<option value="{{ $run->uuid }}" @selected(request('baseline') === $run->uuid)>{{ $run->profile->name }} {{ $run->profile->version }} · {{ $run->dataset->version }} · {{ number_format((float) $run->score * 100, 1) }}%</option>@endforeach</select></div>
            <div class="col-md-5"><select class="form-select" name="candidate" required><option value="">Candidate run</option>@foreach ($completedRuns as $run)<option value="{{ $run->uuid }}" @selected(request('candidate') === $run->uuid)>{{ $run->profile->name }} {{ $run->profile->version }} · {{ $run->dataset->version }} · {{ number_format((float) $run->score * 100, 1) }}%</option>@endforeach</select></div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Compare</button></div>
        </form>
        @if ($comparisonError)<div class="alert alert-warning mb-0">{{ $comparisonError }}</div>@endif
        @if ($comparison)
            <div class="alert {{ $comparison['has_regression'] ? 'alert-danger' : 'alert-success' }} py-2"><strong>{{ $comparison['has_regression'] ? 'Regression detected' : 'No regression detected' }}</strong> · score delta {{ $comparison['score_delta'] >= 0 ? '+' : '' }}{{ number_format($comparison['score_delta'] * 100, 1) }}% · token delta {{ $comparison['token_delta'] >= 0 ? '+' : '' }}{{ number_format($comparison['token_delta']) }}</div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Case</th><th>Baseline</th><th>Candidate</th><th>Delta</th></tr></thead><tbody>@foreach ($comparison['cases'] as $case)<tr class="{{ $case['regressed'] ? 'table-danger' : '' }}"><td>{{ $case['name'] }}</td><td>{{ number_format($case['baseline_score'] * 100, 1) }}%</td><td>{{ number_format($case['candidate_score'] * 100, 1) }}%</td><td>{{ $case['delta'] >= 0 ? '+' : '' }}{{ number_format($case['delta'] * 100, 1) }}%</td></tr>@endforeach</tbody></table></div>
        @endif
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Recent evaluation runs</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Requested</th><th>Profile</th><th>Status</th><th>Score</th><th>Cases</th><th>Tokens</th><th>Results</th><th></th></tr></thead><tbody>
        @forelse ($runs as $run)
            <tr>
                <td><div>{{ $run->created_at->format('d M Y H:i') }}</div><small class="text-muted">{{ $run->requester?->name ?? 'Former user' }}</small></td>
                <td><strong>{{ $run->profile->name }} {{ $run->profile->version }}</strong><div class="small text-muted">{{ $run->dataset ? $run->dataset->name.' '.$run->dataset->version : 'Legacy unversioned run' }}</div></td>
                <td><span class="badge text-bg-{{ $run->status->value === 'completed' ? 'success' : ($run->status->value === 'failed' ? 'danger' : 'secondary') }}">{{ $run->status->label() }}</span>@if ($run->error)<div class="small text-danger mt-1">{{ $run->error }}</div>@endif</td>
                <td>{{ $run->score === null ? '—' : number_format((float) $run->score * 100, 1).'%' }}</td>
                <td>{{ $run->passed_cases }}/{{ $run->total_cases }} passed</td>
                <td>{{ number_format($run->total_tokens) }}</td>
                <td><details><summary>{{ $run->results->count() }} details</summary><ul class="small ps-3 mt-2 mb-0">@foreach ($run->results as $result)<li>{{ $result->evaluationCase?->name ?? 'Deleted case' }}: {{ number_format((float) $result->score * 100, 1) }}% @if ($result->differences) ({{ implode(', ', $result->differences) }}) @endif</li>@endforeach</ul></details></td>
                <td style="min-width:260px">@if ($run->status->value === 'completed' && (float) $run->score >= $approvalThreshold && $run->dataset)<form method="POST" action="{{ route('social.ai-evaluations.runs.approve', [$run->profile, $run]) }}">@csrf<div class="input-group input-group-sm"><input class="form-control" name="release_notes" required maxlength="2000" placeholder="Required release notes"><button class="btn btn-outline-success" @disabled($run->profile->activation_eligible)>Approve</button></div></form>@else<span class="small text-muted">Not eligible</span>@endif</td>
            </tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-4">No evaluation runs yet.</td></tr>@endforelse
    </tbody></table></div>
</div>
@endsection
