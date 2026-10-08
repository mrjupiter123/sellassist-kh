<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Enums\AiProfileReleaseType;
use App\Domain\Social\Exceptions\SocialOrderExtractionException;
use App\Domain\Social\Models\SocialAiExtractionProfile;
use App\Domain\Social\Models\SocialAiProfileRelease;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ActivateAiExtractionProfile
{
    public function execute(SocialAiExtractionProfile $profile, User $actor, string $reason): SocialAiExtractionProfile
    {
        return DB::transaction(function () use ($profile, $actor, $reason): SocialAiExtractionProfile {
            $locked = SocialAiExtractionProfile::query()->lockForUpdate()->findOrFail($profile->id);
            if (! $locked->activation_eligible) {
                throw new SocialOrderExtractionException('Approve a qualifying synthetic evaluation run before activating this profile.');
            }
            if ($locked->active) {
                throw new SocialOrderExtractionException('This AI extraction profile is already active.');
            }

            $previous = SocialAiExtractionProfile::query()->where('active', true)->lockForUpdate()->first();
            $wasPreviouslyActivated = $locked->activated_at !== null || $locked->releases()->exists();
            $releaseType = $previous && $wasPreviouslyActivated
                ? AiProfileReleaseType::Rollback
                : AiProfileReleaseType::Activation;

            SocialAiExtractionProfile::query()->where('active', true)->update(['active' => false]);

            $locked->update([
                'active' => true,
                'activated_by' => $actor->id,
                'activated_at' => now(),
            ]);

            SocialAiProfileRelease::query()->create([
                'social_ai_extraction_profile_id' => $locked->id,
                'previous_profile_id' => $previous?->id,
                'approval_evaluation_run_id' => $locked->approval_evaluation_run_id,
                'type' => $releaseType,
                'reason' => $reason,
                'released_by' => $actor->id,
                'released_at' => now(),
            ]);

            return $locked;
        });
    }
}
