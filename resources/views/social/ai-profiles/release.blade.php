@extends('layouts.app')

@section('title', 'AI release monitoring')

@section('content')
@php
    $release = $monitoring['release'];
    $statusClass = match ($monitoring['status']) {
        'degraded' => 'danger',
        'healthy' => 'success',
        'collecting' => 'warning',
        default => 'secondary',
    };
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1">AI release monitoring</h1><p class="text-muted mb-0">Aggregate before-and-after quality signals. No customer content is displayed.</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('social.ai-profiles.index') }}">Back to profiles</a>
</div>

<div class="card shadow-sm mb-4"><div class="card-body row g-3 align-items-center">
    <div class="col-lg-4"><div class="small text-muted">Released profile</div><strong>{{ $release->profile->name }} {{ $release->profile->version }}</strong><div class="small text-muted">{{ $release->profile->model }}</div></div>
    <div class="col-lg-3"><div class="small text-muted">Previous profile</div><strong>{{ $release->previousProfile ? $release->previousProfile->name.' '.$release->previousProfile->version : 'No managed baseline' }}</strong></div>
    <div class="col-lg-3"><div class="small text-muted">Released</div><strong>{{ $release->released_at->format('d M Y H:i') }}</strong><div class="small text-muted">{{ $release->releaser?->name ?? 'Former user' }}</div></div>
    <div class="col-lg-2 text-lg-end"><span class="badge fs-6 text-bg-{{ $statusClass }}">{{ str($monitoring['status'])->headline() }}</span></div>
    <div class="col-12"><div class="small text-muted">Release reason</div>{{ $release->reason }}</div>
</div></div>

<div class="alert alert-{{ $statusClass }}">
    <strong>{{ $monitoring['recommendation'] }}</strong>
    @if ($monitoring['reasons'])<ul class="mb-0 mt-2">@foreach ($monitoring['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>@endif
</div>

<div class="row g-4 mb-4">
    @foreach ([
        ['title' => 'Previous-profile baseline', 'metrics' => $monitoring['baseline'], 'from' => $monitoring['baseline_from'], 'until' => $monitoring['baseline_until']],
        ['title' => 'Released-profile results', 'metrics' => $monitoring['candidate'], 'from' => $monitoring['candidate_from'], 'until' => $monitoring['candidate_until']],
    ] as $period)
        <div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-header bg-white"><strong>{{ $period['title'] }}</strong><div class="small text-muted">{{ $period['from']->format('d M Y H:i') }} – {{ $period['until']->format('d M Y H:i') }}</div></div><div class="card-body"><div class="row g-3">
            <div class="col-6"><div class="small text-muted">Completed samples</div><div class="h3 mb-0">{{ $period['metrics']['samples'] }}</div></div>
            <div class="col-6"><div class="small text-muted">Success rate</div><div class="h3 mb-0">{{ number_format($period['metrics']['success_rate'] * 100, 1) }}%</div></div>
            <div class="col-6"><div class="small text-muted">Average confidence</div><div class="h3 mb-0">{{ $period['metrics']['average_confidence'] === null ? '—' : number_format($period['metrics']['average_confidence'] * 100, 1).'%' }}</div></div>
            <div class="col-6"><div class="small text-muted">Useful after review</div><div class="h3 mb-0">{{ $period['metrics']['useful_rate'] === null ? '—' : number_format($period['metrics']['useful_rate'] * 100, 1).'%' }}</div><div class="small text-muted">{{ $period['metrics']['reviewed'] }} reviewed</div></div>
            <div class="col-6"><div class="small text-muted">Ready / failed</div><strong>{{ $period['metrics']['ready'] }} / {{ $period['metrics']['failed'] }}</strong></div>
            <div class="col-6"><div class="small text-muted">Average tokens</div><strong>{{ number_format($period['metrics']['average_tokens'], 1) }}</strong></div>
        </div></div></div></div>
    @endforeach
</div>

<div class="card shadow-sm mb-4"><div class="card-header bg-white fw-semibold">Candidate change from baseline</div><div class="card-body row g-3">
    <div class="col-md-4"><div class="small text-muted">Success-rate delta</div><div class="h3 mb-0">{{ $monitoring['deltas']['success_rate'] >= 0 ? '+' : '' }}{{ number_format($monitoring['deltas']['success_rate'] * 100, 1) }}%</div></div>
    <div class="col-md-4"><div class="small text-muted">Confidence delta</div><div class="h3 mb-0">{{ $monitoring['deltas']['average_confidence'] === null ? '—' : ($monitoring['deltas']['average_confidence'] >= 0 ? '+' : '').number_format($monitoring['deltas']['average_confidence'] * 100, 1).'%' }}</div></div>
    <div class="col-md-4"><div class="small text-muted">Reviewed usefulness delta</div><div class="h3 mb-0">{{ $monitoring['deltas']['useful_rate'] === null ? '—' : ($monitoring['deltas']['useful_rate'] >= 0 ? '+' : '').number_format($monitoring['deltas']['useful_rate'] * 100, 1).'%' }}</div></div>
</div></div>

<div class="alert alert-secondary mb-0">Assessment requires at least {{ $monitoring['minimum_samples'] }} completed extractions in both periods. Reviewed usefulness is evaluated only when both periods have at least {{ $monitoring['minimum_reviews'] }} seller reviews. A rollback recommendation is advisory; an administrator must return to AI Profiles and perform it explicitly with a reason.</div>
@endsection
