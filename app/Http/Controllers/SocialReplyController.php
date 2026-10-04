<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\SendSocialReply;
use App\Domain\Social\Models\SocialConversation;
use App\Http\Requests\Social\SendSocialReplyRequest;
use Illuminate\Http\RedirectResponse;

class SocialReplyController extends Controller
{
    public function store(
        SendSocialReplyRequest $request,
        SocialConversation $conversation,
        SendSocialReply $action,
    ): RedirectResponse {
        $action->execute($conversation, $request->validated('body'), $request->user());

        return back()->with('success', 'Reply queued for delivery.');
    }
}
