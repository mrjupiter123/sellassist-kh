<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\UpdateAiAlertPreference;
use App\Http\Requests\Social\UpdateAiAlertPreferenceRequest;
use Illuminate\Http\RedirectResponse;

class SocialAiAlertPreferenceController extends Controller
{
    public function update(UpdateAiAlertPreferenceRequest $request, UpdateAiAlertPreference $action): RedirectResponse
    {
        $action->execute($request->user(), $request->boolean('email_enabled'));

        return redirect()->route('notifications.index')
            ->with('success', 'AI release alert preference updated.');
    }
}
