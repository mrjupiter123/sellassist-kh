<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Models\SocialAiProfileRelease;
use App\Domain\Social\Models\SocialAiProfileReleaseAlert;
use App\Domain\Social\Services\PostReleaseMonitoringService;
use App\Models\User;
use App\Notifications\AiReleaseDegradationDetected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class CheckAiReleaseDegradation
{
    public function __construct(private readonly PostReleaseMonitoringService $monitoring) {}

    /** @return array{status: string, notified: int, release: ?SocialAiProfileRelease} */
    public function execute(): array
    {
        $release = SocialAiProfileRelease::query()
            ->whereHas('profile', fn ($query) => $query->where('active', true))
            ->with('profile:id,uuid,name,version,active')
            ->latest('released_at')
            ->first();
        if (! $release) {
            return ['status' => 'no_release', 'notified' => 0, 'release' => null];
        }

        $summary = $this->monitoring->summarize($release);
        $fingerprint = $summary['status'] === 'degraded'
            ? hash('sha256', json_encode($summary['reasons'], JSON_THROW_ON_ERROR))
            : null;
        $administrators = User::permission('social.ai.manage')->where('active', true)->get();
        $notified = 0;

        DB::transaction(function () use ($release, $summary, $fingerprint, $administrators, &$notified): void {
            $alert = SocialAiProfileReleaseAlert::query()
                ->where('social_ai_profile_release_id', $release->id)
                ->lockForUpdate()
                ->first();
            $unchangedDegradation = $alert?->status === 'degraded'
                && $alert->last_notified_at !== null
                && hash_equals((string) $alert->fingerprint, (string) $fingerprint);

            if ($summary['status'] === 'degraded' && ! $unchangedDegradation) {
                Notification::send($administrators, new AiReleaseDegradationDetected($release, $summary['reasons']));
                $notified = $administrators->count();
            }

            SocialAiProfileReleaseAlert::query()->updateOrCreate(
                ['social_ai_profile_release_id' => $release->id],
                [
                    'status' => $summary['status'],
                    'fingerprint' => $fingerprint,
                    'reasons' => $summary['reasons'],
                    'last_checked_at' => now(),
                    'last_notified_at' => $summary['status'] === 'degraded'
                        && ! $unchangedDegradation
                        && $administrators->isNotEmpty()
                        ? now()
                        : $alert?->last_notified_at,
                    'resolved_at' => $alert?->status === 'degraded' && $summary['status'] !== 'degraded'
                        ? now()
                        : $alert?->resolved_at,
                ],
            );
        });

        return ['status' => $summary['status'], 'notified' => $notified, 'release' => $release];
    }
}
