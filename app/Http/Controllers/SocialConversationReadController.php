<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\MarkSocialConversationUnread;
use App\Domain\Social\Models\SocialConversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SocialConversationReadController extends Controller
{
    public function destroy(
        Request $request,
        SocialConversation $conversation,
        MarkSocialConversationUnread $action,
    ): RedirectResponse {
        $action->execute($conversation, $request->user());

        return redirect()->route('social.inbox.index')->with('success', 'Conversation marked unread.');
    }
}
