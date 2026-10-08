<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAiEvaluationCase extends Model
{
    use HasPublicUuid;

    protected $fillable = ['name', 'locale', 'messages', 'catalog', 'expected_result', 'active', 'created_by'];

    protected function casts(): array
    {
        return [
            'messages' => 'encrypted:array',
            'catalog' => 'encrypted:array',
            'expected_result' => 'encrypted:array',
            'active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(SocialAiEvaluationResult::class, 'social_ai_evaluation_case_id');
    }

    public function datasets(): BelongsToMany
    {
        return $this->belongsToMany(
            SocialAiEvaluationDataset::class,
            'social_ai_evaluation_dataset_cases',
            'social_ai_evaluation_case_id',
            'social_ai_evaluation_dataset_id',
        );
    }
}
