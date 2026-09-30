<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\ConvertConversationToDraftOrder;
use App\Domain\Social\Models\SocialConversation;
use App\Http\Requests\Social\ConvertSocialConversationRequest;
use Illuminate\Http\RedirectResponse;

class SocialOrderController extends Controller
{
    public function store(
        ConvertSocialConversationRequest $request,
        SocialConversation $conversation,
        ConvertConversationToDraftOrder $action,
    ): RedirectResponse {
        $order = $action->execute($conversation, $request->validated(), $request->user());

        return redirect()->route('orders.show', $order)->with('success', 'Draft order created for seller review.');
    }
}
