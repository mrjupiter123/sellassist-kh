<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Models\SocialAiAlertPreference;
use App\Models\User;

final class UpdateAiAlertPreference
{
    public function execute(User $user, bool $emailEnabled): SocialAiAlertPreference
    {
        return SocialAiAlertPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['email_enabled' => $emailEnabled],
        );
    }
}
