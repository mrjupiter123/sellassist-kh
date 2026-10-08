<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAiProfileReleaseAlert extends Model
{
    protected $fillable = [
        'social_ai_profile_release_id', 'status', 'fingerprint', 'reasons',
        'last_checked_at', 'last_notified_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'last_checked_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(SocialAiProfileRelease::class, 'social_ai_profile_release_id');
    }
}
