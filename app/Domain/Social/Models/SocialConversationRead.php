<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialConversationRead extends Model
{
    protected $fillable = ['social_conversation_id', 'user_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SocialConversation::class, 'social_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
