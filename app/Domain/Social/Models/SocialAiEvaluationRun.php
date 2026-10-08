<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Social\Enums\AiEvaluationRunStatus;
use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAiEvaluationRun extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'social_ai_extraction_profile_id', 'social_ai_evaluation_dataset_id', 'status', 'case_ids', 'total_cases', 'passed_cases',
        'failed_cases', 'score', 'total_tokens', 'error', 'requested_by', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiEvaluationRunStatus::class,
            'case_ids' => 'encrypted:array',
            'total_cases' => 'integer',
            'passed_cases' => 'integer',
            'failed_cases' => 'integer',
            'score' => 'decimal:4',
            'total_tokens' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(SocialAiExtractionProfile::class, 'social_ai_extraction_profile_id');
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(SocialAiEvaluationDataset::class, 'social_ai_evaluation_dataset_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(SocialAiEvaluationResult::class, 'social_ai_evaluation_run_id');
    }
}
