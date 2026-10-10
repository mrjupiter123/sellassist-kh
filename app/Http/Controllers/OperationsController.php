<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Operations\Models\QueueStallAlert;
use App\Domain\Operations\Services\AiExtractionQualityService;
use App\Domain\Operations\Services\OperationalHealthService;
use App\Http\Requests\OperationsQualityRequest;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function __invoke(
        OperationsQualityRequest $request,
        OperationalHealthService $health,
        AiExtractionQualityService $quality,
    ): View {
        $days = $request->integer('period', 30);

        return view('operations.index', [
            'health' => $health->summary(),
            'queueStallAlerts' => QueueStallAlert::query()->whereIn('queue', ['integrations', 'default'])->get()->keyBy('queue'),
            'failedJobs' => $health->failedJobs(),
            'aiQuality' => $quality->summary($days),
            'aiModels' => $quality->modelBreakdown($days),
            'aiDailyTrend' => $quality->dailyTrend($days),
            'aiRecentReviews' => $quality->recentReviews($days),
        ]);
    }
}
