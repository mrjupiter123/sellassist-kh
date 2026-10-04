<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\AssignSocialConversation;
use App\Domain\Social\Models\SocialConversation;
use App\Http\Requests\Social\AssignSocialConversationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class SocialConversationAssignmentController extends Controller
{
    public function update(
        AssignSocialConversationRequest $request,
        SocialConversation $conversation,
        AssignSocialConversation $action,
    ): RedirectResponse {
        $assigneeId = $request->validated('assigned_to');
        $assignee = $assigneeId !== null ? User::query()->findOrFail($assigneeId) : null;
        $action->execute($conversation, $assignee, $request->user());

        return back()->with('success', $assignee ? "Conversation assigned to {$assignee->name}." : 'Conversation unassigned.');
    }
}
