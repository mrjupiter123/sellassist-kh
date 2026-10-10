<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class DatabaseQueueStalled extends Notification
{
    public function __construct(
        private readonly string $queue,
        private readonly int $staleJobs,
        private readonly int $thresholdMinutes,
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
            'kind' => 'database_queue_stalled',
            'title' => 'Queue processing needs attention',
            'message' => "The {$this->queue} queue has {$this->staleJobs} ready job(s) waiting at least {$this->thresholdMinutes} minutes. Check the cPanel queue cron and Operations; no jobs were retried automatically.",
            'url' => route('operations.index', [], false),
        ];
    }
}
