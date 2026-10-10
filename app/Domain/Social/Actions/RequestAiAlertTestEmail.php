<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Enums\AiAlertMailStatus;
use App\Domain\Social\Enums\AiAlertMailType;
use App\Domain\Social\Models\AiAlertMailAttempt;
use App\Jobs\SendAiAlertTestEmail;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RequestAiAlertTestEmail
{
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $attempt = AiAlertMailAttempt::query()->create([
                'user_id' => $user->id,
                'type' => AiAlertMailType::Test,
                'status' => AiAlertMailStatus::Queued,
            ]);

            SendAiAlertTestEmail::dispatch($user->id, $attempt->id)
                ->onQueue('integrations')
                ->afterCommit();
        });
    }
}
