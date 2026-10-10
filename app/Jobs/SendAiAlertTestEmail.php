<?php

declare(strict_types=1);

namespace App\Jobs;

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

    public function __construct(public readonly int $userId) {}

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if (! $user?->active || ! $user->can('social.ai.manage')) {
            return;
        }

        Mail::to($user->email)->send(new AiAlertTestMail);
    }
}
