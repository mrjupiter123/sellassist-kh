<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Social\Enums\AiAlertMailStatus;
use App\Domain\Social\Models\AiAlertMailAttempt;
use App\Mail\AiAlertTestMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

final class SendAiAlertTestEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly int $userId,
        public readonly ?int $attemptId = null,
    ) {}

    public function handle(): void
    {
        if ($this->attemptId !== null && ! AiAlertMailAttempt::query()
            ->whereKey($this->attemptId)
            ->whereIn('status', [AiAlertMailStatus::Queued->value, AiAlertMailStatus::Failed->value])
            ->exists()) {
            return;
        }

        $user = User::query()->find($this->userId);

        if (! $user?->active || ! $user->can('social.ai.manage')) {
            $this->finish(AiAlertMailStatus::Skipped);

            return;
        }

        Mail::to($user->email)->send(new AiAlertTestMail);
        $this->finish(AiAlertMailStatus::Sent);
    }

    public function failed(\Throwable $exception): void
    {
        $this->finish(AiAlertMailStatus::Failed);
    }

    private function finish(AiAlertMailStatus $status): void
    {
        if ($this->attemptId !== null) {
            AiAlertMailAttempt::query()->whereKey($this->attemptId)
                ->whereIn('status', [AiAlertMailStatus::Queued->value, AiAlertMailStatus::Failed->value])
                ->update(['status' => $status->value, 'processed_at' => now()]);
        }
    }
}
