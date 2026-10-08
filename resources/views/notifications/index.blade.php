@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1">Notifications</h1><p class="text-muted mb-0">Operational alerts addressed to your account.</p></div>
    @if (auth()->user()->unreadNotifications()->exists())<form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-outline-secondary">Mark all as read</button></form>@endif
</div>

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
