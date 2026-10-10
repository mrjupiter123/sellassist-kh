<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Models\AiAlertMailAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->paginate(20),
            'aiAlertPreference' => $request->user()->can('social.ai.manage')
                ? $request->user()->socialAiAlertPreference()->first()
                : null,
            'aiAlertMailAttempts' => $request->user()->can('social.ai.manage')
                ? AiAlertMailAttempt::query()->where('user_id', $request->user()->id)->latest()->limit(10)->get()
                : collect(),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
