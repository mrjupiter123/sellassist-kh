<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\ChangeSocialConversationStatus;
use App\Domain\Social\Enums\ConversationStatus;
use App\Domain\Social\Models\SocialConversation;
use App\Http\Requests\Social\ChangeSocialConversationStatusRequest;
use Illuminate\Http\RedirectResponse;

class SocialConversationStatusController extends Controller
{
    public function update(
        ChangeSocialConversationStatusRequest $request,
        SocialConversation $conversation,
        ChangeSocialConversationStatus $action,
    ): RedirectResponse {
        $updated = $action->execute(
            $conversation,
            ConversationStatus::from($request->validated('status')),
            $request->user(),
        );

        return back()->with('success', "Conversation marked {$updated->status->label()}.");
    }
}
