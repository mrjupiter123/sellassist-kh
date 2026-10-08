<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\ReviewSocialOrderExtraction;
use App\Domain\Social\Models\SocialConversation;
use App\Domain\Social\Models\SocialOrderExtraction;
use App\Http\Requests\Social\ReviewSocialOrderExtractionRequest;
use Illuminate\Http\RedirectResponse;

class SocialOrderExtractionReviewController extends Controller
{
    public function update(
        ReviewSocialOrderExtractionRequest $request,
        SocialConversation $conversation,
        SocialOrderExtraction $orderExtraction,
        ReviewSocialOrderExtraction $action,
    ): RedirectResponse {
        $action->execute($orderExtraction, $request->validated(), $request->user());

        return redirect()
            ->route('social.inbox.show', $conversation)
            ->with('success', 'AI suggestion feedback saved.');
    }
}
