<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Social\Models\SocialAiProfileRelease;
use App\Domain\Social\Models\SocialAiProfileReleaseAlert;
use App\Mail\AiReleaseDegradationMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

final class SendAiReleaseDegradationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /** @param list<string> $reasons */
    public function __construct(
        public readonly int $userId,
        public readonly int $releaseId,
        public readonly array $reasons,
    ) {}

    public function handle(): void
    {
        $user = User::query()->with('socialAiAlertPreference')->find($this->userId);
        if (! $user?->active
            || ! $user->can('social.ai.manage')
            || ! $user->socialAiAlertPreference?->email_enabled) {
            return;
        }

        $release = SocialAiProfileRelease::query()->with('profile:id,name,version,active')->find($this->releaseId);
        $alert = SocialAiProfileReleaseAlert::query()
            ->where('social_ai_profile_release_id', $this->releaseId)
            ->first();
        if (! $release?->profile?->active
            || $alert?->status !== 'degraded'
            || ! hash_equals((string) $alert->fingerprint, hash('sha256', json_encode($this->reasons, JSON_THROW_ON_ERROR)))) {
            return;
        }

        Mail::to($user->email)->send(new AiReleaseDegradationMail(
            $release->profile->name,
            $release->profile->version,
            $release->uuid,
            $this->reasons,
        ));
    }
}
