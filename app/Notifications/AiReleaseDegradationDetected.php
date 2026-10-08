<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Social\Models\SocialAiProfileRelease;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AiReleaseDegradationDetected extends Notification
{
    use Queueable;

    /** @param list<string> $reasons */
    public function __construct(
        private readonly SocialAiProfileRelease $release,
        private readonly array $reasons,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'ai_release_degraded',
            'title' => 'AI release degradation detected',
            'message' => "Manual rollback review is recommended for {$this->release->profile->name} {$this->release->profile->version}.",
            'reasons' => $this->reasons,
            'release_uuid' => $this->release->uuid,
            'url' => route('social.ai-profile-releases.show', $this->release, false),
        ];
    }
}
