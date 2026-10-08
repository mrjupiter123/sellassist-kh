<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Social\Enums\AiEvaluationResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAiEvaluationResult extends Model
{
    protected $fillable = [
        'social_ai_evaluation_run_id', 'social_ai_evaluation_case_id', 'status',
        'score', 'passed', 'actual_result', 'differences', 'provider_response_id',
        'total_tokens', 'error', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiEvaluationResultStatus::class,
            'score' => 'decimal:4',
            'passed' => 'boolean',
            'actual_result' => 'encrypted:array',
            'differences' => 'encrypted:array',
            'total_tokens' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SocialAiEvaluationRun::class, 'social_ai_evaluation_run_id');
    }

    public function evaluationCase(): BelongsTo
    {
        return $this->belongsTo(SocialAiEvaluationCase::class, 'social_ai_evaluation_case_id');
    }
}
