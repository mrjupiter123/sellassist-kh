<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\RequestSocialOrderExtraction;
use App\Domain\Social\Enums\OrderExtractionStatus;
use App\Domain\Social\Models\SocialConversation;
use App\Http\Requests\Social\RequestSocialOrderExtractionRequest;
use Illuminate\Http\RedirectResponse;

class SocialOrderExtractionController extends Controller
{
    public function store(
        RequestSocialOrderExtractionRequest $request,
        SocialConversation $conversation,
        RequestSocialOrderExtraction $action,
    ): RedirectResponse {
        $extraction = $action->execute($conversation, $request->user());
        $message = $extraction->status === OrderExtractionStatus::Ready
            ? 'The current AI suggestions are already available for review.'
            : 'AI order suggestion queued. Refresh this page after the integrations worker processes it.';

        return redirect()->route('social.inbox.show', $conversation)->with('success', $message);
    }
}
