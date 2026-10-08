<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\ApproveAiExtractionProfile;
use App\Domain\Social\Actions\RequestAiEvaluationRun;
use App\Domain\Social\Models\SocialAiEvaluationCase;
use App\Domain\Social\Models\SocialAiEvaluationRun;
use App\Domain\Social\Models\SocialAiExtractionProfile;
use App\Http\Requests\Social\ApproveAiEvaluationRequest;
use App\Http\Requests\Social\RunAiEvaluationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SocialAiEvaluationController extends Controller
{
    public function index(): View
    {
        return view('social.ai-evaluations.index', [
            'cases' => SocialAiEvaluationCase::query()->where('active', true)->orderBy('locale')->orderBy('name')->get(['id', 'uuid', 'name', 'locale']),
            'profiles' => SocialAiExtractionProfile::query()->latest()->get(['id', 'uuid', 'name', 'version', 'model', 'active', 'activation_eligible']),
            'runs' => SocialAiEvaluationRun::query()
                ->with(['profile:id,uuid,name,version,model,activation_eligible', 'requester:id,uuid,name', 'results.evaluationCase:id,uuid,name,locale'])
                ->latest()->limit(20)->get(),
            'approvalThreshold' => (float) config('social.ai.evaluation_approval_threshold', 0.85),
        ]);
    }

    public function store(
        RunAiEvaluationRequest $request,
        SocialAiExtractionProfile $profile,
        RequestAiEvaluationRun $action,
    ): RedirectResponse {
        $action->execute($profile, $request->user());

        return redirect()->route('social.ai-evaluations.index')
            ->with('success', 'Synthetic evaluation queued. Refresh after the integrations worker processes it.');
    }

    public function approve(
        ApproveAiEvaluationRequest $request,
        SocialAiExtractionProfile $profile,
        SocialAiEvaluationRun $evaluationRun,
        ApproveAiExtractionProfile $action,
    ): RedirectResponse {
        $action->execute($profile, $evaluationRun, $request->user());

        return redirect()->route('social.ai-evaluations.index')
            ->with('success', 'Profile approved for activation. Activation remains a separate administrator action.');
    }
}
