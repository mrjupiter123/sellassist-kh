<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Social\Models\SocialAiExtractionProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ActivateAiExtractionProfile
{
    public function execute(SocialAiExtractionProfile $profile, User $actor): SocialAiExtractionProfile
    {
        return DB::transaction(function () use ($profile, $actor): SocialAiExtractionProfile {
            $locked = SocialAiExtractionProfile::query()->lockForUpdate()->findOrFail($profile->id);
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
