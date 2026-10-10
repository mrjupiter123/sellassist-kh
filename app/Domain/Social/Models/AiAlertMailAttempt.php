<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Social\Enums\AiAlertMailStatus;
use App\Domain\Social\Enums\AiAlertMailType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAlertMailAttempt extends Model
{
    protected $fillable = ['user_id', 'release_id', 'type', 'status', 'processed_at'];

    protected function casts(): array
    {
        return [
            'type' => AiAlertMailType::class,
            'status' => AiAlertMailStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(SocialAiProfileRelease::class, 'release_id');
    }
}
