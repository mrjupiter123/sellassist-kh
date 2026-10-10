<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Jobs\SendAiAlertTestEmail;
use App\Models\User;

final class RequestAiAlertTestEmail
{
    public function execute(User $user): void
    {
        SendAiAlertTestEmail::dispatch($user->id)->onQueue('integrations');
    }
}
