<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAiAlertPreference extends Model
{
    protected $fillable = ['user_id', 'email_enabled'];

    protected function casts(): array
    {
        return ['email_enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
