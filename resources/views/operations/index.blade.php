@extends('layouts.app')

@section('title', 'Operations health')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Operations health</h1><p class="text-muted mb-0">Queue and integration signals for administrators.</p></div>

<div class="row g-3 mb-4">
    @foreach ([
        'Pending jobs' => $health['pending_jobs'],
        'Oldest pending (minutes)' => $health['oldest_pending_minutes'] ?? '—',
        'Failed jobs' => $health['failed_jobs'],
        'Delivery failures (24h)' => $health['delivery_failures_24h'],
        'Delivery webhooks waiting' => $health['delivery_webhooks_waiting'],
        'Social webhooks waiting' => $health['social_webhooks_waiting'],
    ] as $label => $value)
        <div class="col-6 col-lg-4"><div class="card shadow-sm h-100"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="display-6">{{ $value }}</div></div></div></div>
    @endforeach
</div>

<div class="card shadow-sm mb-4"><div class="card-body row g-3"><div class="col-md-6"><div class="small text-muted">Last successful delivery integration</div><strong>{{ $health['last_delivery_success'] ?: 'No success recorded' }}</strong></div><div class="col-md-6"><div class="small text-muted">Last processed social webhook</div><strong>{{ $health['last_social_success'] ?: 'No success recorded' }}</strong></div></div></div>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div><h2 class="h4 mb-1">AI extraction quality</h2><p class="text-muted mb-0">Aggregate operational metrics only; customer messages and encrypted correction notes are never shown here.</p></div>
    <form method="GET" action="{{ route('operations.index') }}" class="d-flex align-items-end gap-2">
        <div><label class="form-label small" for="quality-period">Period</label><select class="form-select" id="quality-period" name="period">@foreach ([7, 30, 90] as $period)<option value="{{ $period }}" @selected($aiQuality['period_days'] === $period)>Last {{ $period }} days</option>@endforeach</select></div>
        <button class="btn btn-outline-primary">Apply</button>
    </form>
</div>

<div class="row g-3 mb-4">
    @foreach ([
        'Extractions' => number_format($aiQuality['total']),
        'Success rate' => number_format($aiQuality['success_rate'], 1).'%',
        'Average confidence' => $aiQuality['average_confidence'] === null ? '—' : number_format($aiQuality['average_confidence'] * 100, 1).'%',
        'Low confidence' => number_format($aiQuality['low_confidence']),
        'Reviewed' => number_format($aiQuality['reviewed']).' ('.number_format($aiQuality['review_rate'], 1).'%)',
        'Useful after review' => number_format($aiQuality['useful_rate'], 1).'%',
        'Total tokens' => number_format($aiQuality['total_tokens']),
    ] as $label => $value)
        <div class="col-6 col-lg-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="h3 mb-0">{{ $value }}</div></div></div></div>
    @endforeach
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Quality by model</div>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Model</th><th>Total</th><th>Ready</th><th>Failed</th><th>Avg. confidence</th><th>Tokens</th></tr></thead><tbody>
                @forelse ($aiModels as $model)
                    <tr><td>{{ $model->model }}</td><td>{{ number_format($model->total) }}</td><td>{{ number_format($model->ready) }}</td><td>{{ number_format($model->failed) }}</td><td>{{ $model->average_confidence === null ? '—' : number_format($model->average_confidence * 100, 1).'%' }}</td><td>{{ number_format($model->total_tokens) }}</td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No AI extractions in this period.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Review outcomes</div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-4"><div class="small text-muted">Accepted</div><div class="h3 text-success">{{ $aiQuality['accepted'] }}</div></div>
                    <div class="col-4"><div class="small text-muted">Corrected</div><div class="h3 text-warning">{{ $aiQuality['corrected'] }}</div></div>
                    <div class="col-4"><div class="small text-muted">Rejected</div><div class="h3 text-danger">{{ $aiQuality['rejected'] }}</div></div>
                </div>
                <hr>
                <div class="d-flex justify-content-between"><span>Ready</span><strong>{{ $aiQuality['ready'] }}</strong></div>
                <div class="d-flex justify-content-between"><span>Failed</span><strong>{{ $aiQuality['failed'] }}</strong></div>
                <div class="d-flex justify-content-between"><span>Still pending</span><strong>{{ $aiQuality['pending'] }}</strong></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Daily usage (maximum 30 days)</div>
            <div class="table-responsive" style="max-height: 360px"><table class="table table-sm align-middle mb-0"><thead class="sticky-top bg-white"><tr><th>Date</th><th>Total</th><th>Ready</th><th>Failed</th><th>Tokens</th></tr></thead><tbody>
                @forelse ($aiDailyTrend as $day)
                    <tr><td>{{ $day->day }}</td><td>{{ $day->total }}</td><td>{{ $day->ready }}</td><td>{{ $day->failed }}</td><td>{{ number_format($day->total_tokens) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No daily usage recorded.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Recent seller reviews</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Reviewed</th><th>Result</th><th>Checks</th><th>Staff</th><th></th></tr></thead><tbody>
                @forelse ($aiRecentReviews as $review)
                    <tr>
                        <td class="text-nowrap">{{ $review->reviewed_at->format('d M H:i') }}</td>
                        <td>{{ $review->verdict->label() }}</td>
                        <td class="text-nowrap" title="Customer / products / quantities">{{ $review->customer_fields_correct === null ? '—' : ($review->customer_fields_correct ? '✓' : '✕') }} / {{ $review->item_matches_correct === null ? '—' : ($review->item_matches_correct ? '✓' : '✕') }} / {{ $review->quantities_correct === null ? '—' : ($review->quantities_correct ? '✓' : '✕') }}</td>
                        <td>{{ $review->reviewer?->name ?? 'Former user' }}</td>
                        <td><a class="btn btn-outline-secondary btn-sm" href="{{ route('social.inbox.show', $review->extraction->conversation) }}">Review</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No seller feedback in this period.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Failed jobs</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Failed</th><th>Queue</th><th>Error</th><th></th></tr></thead><tbody>
    @forelse ($failedJobs as $job)
        <tr><td class="text-nowrap">{{ $job->failed_at }}</td><td>{{ $job->queue }}</td><td><span class="d-inline-block text-truncate" style="max-width: 520px" title="{{ $job->exception }}">{{ $job->exception }}</span></td><td>@can('operations.retry')<form method="POST" action="{{ route('operations.failed-jobs.retry', $job->uuid) }}">@csrf<button class="btn btn-outline-primary btn-sm">Retry safely</button></form>@endcan</td></tr>
    @empty<tr><td colspan="4" class="text-center text-muted py-4">No failed jobs.</td></tr>@endforelse
    </tbody></table></div>
</div>
<div class="mt-3">{{ $failedJobs->links() }}</div>
@endsection
