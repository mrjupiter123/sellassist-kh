<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\ApproveAiExtractionProfile;
use App\Domain\Social\Actions\CreateAiEvaluationCase;
use App\Domain\Social\Actions\CreateAiEvaluationDataset;
use App\Domain\Social\Actions\RequestAiEvaluationRun;
use App\Domain\Social\Enums\AiEvaluationRunStatus;
use App\Domain\Social\Exceptions\SocialOrderExtractionException;
use App\Domain\Social\Models\SocialAiEvaluationCase;
use App\Domain\Social\Models\SocialAiEvaluationDataset;
use App\Domain\Social\Models\SocialAiEvaluationRun;
use App\Domain\Social\Models\SocialAiExtractionProfile;
use App\Domain\Social\Services\AiEvaluationComparisonService;
use App\Http\Requests\Social\ApproveAiEvaluationRequest;
use App\Http\Requests\Social\CompareAiEvaluationRequest;
use App\Http\Requests\Social\RunAiEvaluationRequest;
use App\Http\Requests\Social\StoreAiEvaluationCaseRequest;
use App\Http\Requests\Social\StoreAiEvaluationDatasetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SocialAiEvaluationController extends Controller
{
    public function index(CompareAiEvaluationRequest $request, AiEvaluationComparisonService $comparisonService): View
    {
        $comparison = null;
        $comparisonError = null;
        if ($request->filled(['baseline', 'candidate'])) {
            try {
                $comparison = $comparisonService->compare(
                    SocialAiEvaluationRun::query()->where('uuid', $request->string('baseline'))->firstOrFail(),
                    SocialAiEvaluationRun::query()->where('uuid', $request->string('candidate'))->firstOrFail(),
                );
            } catch (SocialOrderExtractionException $exception) {
                $comparisonError = $exception->getMessage();
            }
        }

        return view('social.ai-evaluations.index', [
            'cases' => SocialAiEvaluationCase::query()->where('active', true)->orderBy('locale')->orderBy('name')->get(['id', 'uuid', 'name', 'locale']),
            'profiles' => SocialAiExtractionProfile::query()->latest()->get(['id', 'uuid', 'name', 'version', 'model', 'active', 'activation_eligible']),
            'datasets' => SocialAiEvaluationDataset::query()->withCount('cases')->latest()->get(),
            'runs' => SocialAiEvaluationRun::query()
                ->with(['dataset:id,uuid,name,version', 'profile:id,uuid,name,version,model,activation_eligible', 'requester:id,uuid,name', 'results.evaluationCase:id,uuid,name,locale'])
                ->latest()->limit(20)->get(),
            'completedRuns' => SocialAiEvaluationRun::query()
                ->where('status', AiEvaluationRunStatus::Completed)->whereNotNull('social_ai_evaluation_dataset_id')
                ->with(['dataset:id,uuid,name,version', 'profile:id,uuid,name,version'])
                ->latest()->limit(50)->get(),
            'comparison' => $comparison,
            'comparisonError' => $comparisonError,
            'approvalThreshold' => (float) config('social.ai.evaluation_approval_threshold', 0.85),
            'regressionTolerance' => (float) config('social.ai.evaluation_regression_tolerance', 0.02),
        ]);
    }

    public function storeCase(StoreAiEvaluationCaseRequest $request, CreateAiEvaluationCase $action): RedirectResponse
    {
        $action->execute(
            $request->string('name')->trim()->toString(),
            $request->string('locale')->toString(),
            $request->messageLines(),
            $request->catalog(),
            $request->expectedResult(),
            $request->user(),
        );

        return redirect()->route('social.ai-evaluations.index')
            ->with('success', 'Synthetic evaluation case created. Existing dataset versions were not changed.');
    }

    public function storeDataset(StoreAiEvaluationDatasetRequest $request, CreateAiEvaluationDataset $action): RedirectResponse
    {
        $validated = $request->validated();
        $action->execute(
            $request->string('name')->trim()->toString(),
            $request->string('version')->toString(),
            $request->string('release_notes')->toString(),
            array_map('intval', $validated['case_ids']),
            $request->user(),
        );

        return redirect()->route('social.ai-evaluations.index')
            ->with('success', 'Frozen evaluation dataset version created. Its case membership cannot be edited.');
    }

    public function storeRun(
        RunAiEvaluationRequest $request,
        SocialAiExtractionProfile $profile,
        RequestAiEvaluationRun $action,
    ): RedirectResponse {
        $dataset = SocialAiEvaluationDataset::query()
            ->where('uuid', $request->validated('dataset_id'))
            ->firstOrFail();
        $action->execute($profile, $dataset, $request->user());

        return redirect()->route('social.ai-evaluations.index')
            ->with('success', 'Synthetic evaluation queued. Refresh after the integrations worker processes it.');
    }

    public function approve(
        ApproveAiEvaluationRequest $request,
        SocialAiExtractionProfile $profile,
        SocialAiEvaluationRun $evaluationRun,
        ApproveAiExtractionProfile $action,
    ): RedirectResponse {
        $action->execute(
            $profile,
            $evaluationRun,
            $request->user(),
            $request->string('release_notes')->toString(),
        );

        return redirect()->route('social.ai-evaluations.index')
            ->with('success', 'Profile approved for activation. Activation remains a separate administrator action.');
    }
}
