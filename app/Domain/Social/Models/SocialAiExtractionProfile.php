<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAiExtractionProfile extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'name', 'version', 'model', 'instructions', 'active', 'activation_eligible',
        'created_by', 'activated_by', 'activated_at', 'approved_by',
        'approval_evaluation_run_id', 'approval_baseline_run_id', 'approval_score_delta',
        'approval_release_notes', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'activation_eligible' => 'boolean',
            'activated_at' => 'datetime',
            'approved_at' => 'datetime',
            'approval_score_delta' => 'decimal:4',
        ];
    }

    public function extractions(): HasMany
    {
        return $this->hasMany(SocialOrderExtraction::class, 'social_ai_extraction_profile_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function evaluationRuns(): HasMany
    {
        return $this->hasMany(SocialAiEvaluationRun::class, 'social_ai_extraction_profile_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvalRun(): BelongsTo
    {
        return $this->belongsTo(SocialAiEvaluationRun::class, 'approval_evaluation_run_id');
    }

    public function approvalBaselineRun(): BelongsTo
    {
        return $this->belongsTo(SocialAiEvaluationRun::class, 'approval_baseline_run_id');
    }
}
