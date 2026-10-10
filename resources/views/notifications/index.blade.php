@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1">Notifications</h1><p class="text-muted mb-0">Operational alerts addressed to your account.</p></div>
    @if (auth()->user()->unreadNotifications()->exists())<form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-outline-secondary">Mark all as read</button></form>@endif
</div>

@can('social.ai.manage')
<form method="POST" action="{{ route('notifications.ai-alert-preference.update') }}" class="card shadow-sm mb-4">
    @csrf
    @method('PUT')
    <div class="card-header bg-white fw-semibold">AI release alert delivery</div>
    <div class="card-body">
        <p class="small text-muted">Degradation alerts always appear here. Email is optional and uses your account email address.</p>
        @if (config('mail.default') === 'log')
            <div class="alert alert-warning small mb-3">The mailer is set to log. Test and alert emails will be written to the application log, not delivered to an inbox.</div>
        @endif
        <input type="hidden" name="email_enabled" value="0">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="ai-alert-email" name="email_enabled" value="1" @checked($aiAlertPreference?->email_enabled)>
            <label class="form-check-label" for="ai-alert-email">Email me when an AI release degrades</label>
        </div>
    </div>
    <div class="card-footer bg-white text-end"><button class="btn btn-outline-primary btn-sm">Save preference</button></div>
</form>
<form method="POST" action="{{ route('notifications.ai-alert-test-email') }}" class="mb-4">
    @csrf
    <button class="btn btn-outline-secondary btn-sm">Send test email to my account</button>
    <span class="small text-muted ms-2">Queued separately from your alert preference; limited to three requests per minute.</span>
</form>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Recent AI alert email attempts</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th scope="col">Requested</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Processed</th></tr></thead>
                <tbody>
                    @forelse ($aiAlertMailAttempts as $attempt)
                        <tr>
                            <td>{{ $attempt->created_at->format('d M Y H:i') }}</td>
                            <td>{{ $attempt->type->label() }}</td>
                            <td>{{ $attempt->status->label() }}</td>
                            <td>{{ $attempt->processed_at?->format('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted text-center py-3">No email attempts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white small text-muted">“Handed to mailer” means the configured mail transport accepted the message; inbox delivery is not guaranteed. A log mailer writes it to the application log.</div>
</div>
@endcan

<div class="card shadow-sm">
    <div class="list-group list-group-flush">
        @forelse ($notifications as $notification)
            <div class="list-group-item {{ $notification->read_at ? '' : 'bg-warning-subtle' }}">
                <div class="d-flex flex-wrap justify-content-between gap-3">
                    <div>
                        <div class="fw-semibold">{{ $notification->data['title'] ?? 'Operational notification' }}</div>
                        <div>{{ $notification->data['message'] ?? '' }}</div>
                        @if (! empty($notification->data['reasons']))<ul class="small mb-0 mt-2">@foreach ($notification->data['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>@endif
                        <div class="small text-muted mt-2">{{ $notification->created_at->format('d M Y H:i') }}</div>
                    </div>
                    <div class="d-flex align-items-start gap-2">
                        @if (! empty($notification->data['url']))<a class="btn btn-outline-primary btn-sm" href="{{ $notification->data['url'] }}">Review</a>@endif
                        @if (! $notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf<button class="btn btn-outline-secondary btn-sm">Mark read</button></form>@endif
                    </div>
                </div>
            </div>
        @empty
            <div class="list-group-item text-center text-muted py-5">No notifications.</div>
        @endforelse
    </div>
</div>
<div class="mt-3">{{ $notifications->links() }}</div>
@endsection
