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
