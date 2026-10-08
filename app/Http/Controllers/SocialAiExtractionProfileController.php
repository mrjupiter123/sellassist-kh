<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\ActivateAiExtractionProfile;
use App\Domain\Social\Actions\CreateAiExtractionProfile;
use App\Domain\Social\Enums\OrderExtractionStatus;
use App\Domain\Social\Models\SocialAiExtractionProfile;
use App\Http\Requests\Social\ActivateAiExtractionProfileRequest;
use App\Http\Requests\Social\StoreAiExtractionProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SocialAiExtractionProfileController extends Controller
{
    public function index(): View
    {
        $profiles = SocialAiExtractionProfile::query()
            ->with(['creator:id,uuid,name', 'activator:id,uuid,name', 'approver:id,uuid,name', 'approvalRun:id,uuid,score'])
            ->withCount([
                'extractions',
                'extractions as ready_extractions_count' => fn ($query) => $query->where('status', OrderExtractionStatus::Ready),
                'extractions as failed_extractions_count' => fn ($query) => $query->where('status', OrderExtractionStatus::Failed),
            ])
            ->withAvg('extractions as average_confidence', 'overall_confidence')
            ->withSum('extractions as total_tokens', 'total_tokens')
            ->latest('created_at')
            ->get();

        return view('social.ai-profiles.index', compact('profiles'));
    }

    public function store(StoreAiExtractionProfileRequest $request, CreateAiExtractionProfile $action): RedirectResponse
    {
        $profile = $action->execute($request->validated(), $request->user());

        return redirect()->route('social.ai-profiles.index')
            ->with('success', "AI extraction profile {$profile->name} {$profile->version} created.");
    }

    public function activate(
        ActivateAiExtractionProfileRequest $request,
        SocialAiExtractionProfile $profile,
        ActivateAiExtractionProfile $action,
    ): RedirectResponse {
        $action->execute($profile, $request->user());

        return redirect()->route('social.ai-profiles.index')
            ->with('success', "AI extraction profile {$profile->name} {$profile->version} activated.");
    }
}
