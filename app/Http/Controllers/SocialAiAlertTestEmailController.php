<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\RequestAiAlertTestEmail;
use App\Http\Requests\Social\SendAiAlertTestEmailRequest;
use Illuminate\Http\RedirectResponse;

class SocialAiAlertTestEmailController extends Controller
{
    public function __invoke(SendAiAlertTestEmailRequest $request, RequestAiAlertTestEmail $action): RedirectResponse
    {
        $action->execute($request->user());

        return redirect()->route('notifications.index')
            ->with('success', 'Test email queued for your account address. Check your inbox after the integrations queue runs.');
    }
}
