<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Models\SocialAiProfileRelease;
use App\Domain\Social\Services\PostReleaseMonitoringService;
use Illuminate\View\View;

class SocialAiProfileReleaseController extends Controller
{
    public function show(
        SocialAiProfileRelease $release,
        PostReleaseMonitoringService $monitoring,
    ): View {
        return view('social.ai-profiles.release', [
            'monitoring' => $monitoring->summarize($release),
        ]);
    }
}
