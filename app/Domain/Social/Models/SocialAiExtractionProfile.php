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
        'name', 'version', 'model', 'instructions', 'active',
        'created_by', 'activated_by', 'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'activated_at' => 'datetime',
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
}
