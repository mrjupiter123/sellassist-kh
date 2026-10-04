<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Social\Enums\MessageDirection;
use App\Domain\Social\Enums\MessageType;
use App\Domain\Social\Enums\SocialMessageDeliveryStatus;
use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMessage extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'social_conversation_id', 'external_id', 'direction', 'delivery_status', 'type', 'body',
        'attachments', 'raw_payload', 'delivery_error', 'sent_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'delivery_status' => SocialMessageDeliveryStatus::class,
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
