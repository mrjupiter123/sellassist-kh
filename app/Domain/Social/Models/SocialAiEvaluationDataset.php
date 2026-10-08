<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Social\Enums\AiEvaluationDatasetStatus;
use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAiEvaluationDataset extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'name', 'version', 'release_notes', 'status', 'created_by', 'frozen_by', 'frozen_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiEvaluationDatasetStatus::class,
            'frozen_at' => 'datetime',
        ];
    }

    public function cases(): BelongsToMany
    {
        return $this->belongsToMany(
            SocialAiEvaluationCase::class,
            'social_ai_evaluation_dataset_cases',
            'social_ai_evaluation_dataset_id',
            'social_ai_evaluation_case_id',
        )->withPivot('position')->orderByPivot('position');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(SocialAiEvaluationRun::class, 'social_ai_evaluation_dataset_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function freezer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by');
    }
}
