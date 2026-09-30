<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Social\Enums\MessageDirection;
use App\Domain\Social\Enums\MessageType;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMessage extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'social_conversation_id', 'external_id', 'direction', 'type', 'body',
        'attachments', 'raw_payload', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'type' => MessageType::class,
            'body' => 'encrypted',
            'attachments' => 'encrypted:array',
            'raw_payload' => 'encrypted:array',
            'sent_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SocialConversation::class, 'social_conversation_id');
    }
}
