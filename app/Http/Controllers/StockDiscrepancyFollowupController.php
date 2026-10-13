<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\CompleteDiscrepancyFollowup;
use App\Domain\Inventory\Actions\CreateDiscrepancyFollowup;
use App\Domain\Inventory\Actions\ListDiscrepancyFollowups;
use App\Domain\Inventory\Enums\DiscrepancyFollowupStatus;
use App\Domain\Inventory\Models\StockCountDiscrepancyReview;
use App\Domain\Inventory\Models\StockDiscrepancyFollowup;
use App\Http\Requests\Inventory\CompleteDiscrepancyFollowupRequest;
use App\Http\Requests\Inventory\IndexDiscrepancyFollowupsRequest;
use App\Http\Requests\Inventory\StoreDiscrepancyFollowupRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockDiscrepancyFollowupController extends Controller
{
    public function index(IndexDiscrepancyFollowupsRequest $request, ListDiscrepancyFollowups $list): View
    {
        $status = $request->validated('status');

        return view('inventory.discrepancy-followups', [
            'followups' => $list->execute($request->user(), $status),
            'status' => $status,
            'statuses' => DiscrepancyFollowupStatus::cases(),
        ]);
    }

    public function store(StoreDiscrepancyFollowupRequest $request, StockCountDiscrepancyReview $review, CreateDiscrepancyFollowup $action): RedirectResponse
    {
        $data = $request->validated();
        $assignee = User::query()->where('uuid', $data['assignee'])->firstOrFail();
        $followup = $action->execute($review, $assignee, $request->user(), $data['title'], $data['due_on'] ?? null);

        return redirect()->route('inventory.followups.show', $followup)->with('success', 'Corrective follow-up assigned.');
    }

    public function show(StockDiscrepancyFollowup $followup): View
    {
        abort_unless(auth()->user()->can('inventory.review') || $followup->assigned_to === auth()->id(), 403);

        return view('inventory.discrepancy-followup-show', [
            'followup' => $followup->load(['review.stockCount.product:id,uuid,name', 'review.stockCount.variant:id,uuid,color,size', 'assignee:id,name', 'creator:id,name', 'completer:id,name']),
        ]);
    }

    public function complete(CompleteDiscrepancyFollowupRequest $request, StockDiscrepancyFollowup $followup, CompleteDiscrepancyFollowup $action): RedirectResponse
    {
        $action->execute($followup, $request->user(), $request->validated('outcome_note'));

        return redirect()->route('inventory.followups.show', $followup)->with('success', 'Follow-up completed.');
    }
}
