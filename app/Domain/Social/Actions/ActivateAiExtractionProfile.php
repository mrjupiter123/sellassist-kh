<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Exceptions\SocialOrderExtractionException;
use App\Domain\Social\Models\SocialAiExtractionProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ActivateAiExtractionProfile
{
    public function execute(SocialAiExtractionProfile $profile, User $actor): SocialAiExtractionProfile
    {
        return DB::transaction(function () use ($profile, $actor): SocialAiExtractionProfile {
            $locked = SocialAiExtractionProfile::query()->lockForUpdate()->findOrFail($profile->id);
            if (! $locked->activation_eligible) {
                throw new SocialOrderExtractionException('Approve a qualifying synthetic evaluation run before activating this profile.');
            }
            SocialAiExtractionProfile::query()->where('active', true)->where('id', '!=', $locked->id)->update(['active' => false]);

            $locked->update([
                'active' => true,
                'activated_by' => $actor->id,
                'activated_at' => now(),
            ]);

            return $locked;
        });
    }
}
