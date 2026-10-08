<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Social\Enums\AiProfileReleaseType;
use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAiProfileRelease extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'social_ai_extraction_profile_id', 'previous_profile_id', 'approval_evaluation_run_id',
        'type', 'reason', 'released_by', 'released_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AiProfileReleaseType::class,
            'released_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(SocialAiExtractionProfile::class, 'social_ai_extraction_profile_id');
    }

    public function previousProfile(): BelongsTo
    {
        return $this->belongsTo(SocialAiExtractionProfile::class, 'previous_profile_id');
    }

    public function approvalRun(): BelongsTo
    {
        return $this->belongsTo(SocialAiEvaluationRun::class, 'approval_evaluation_run_id');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
