<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Order\Models\Order;
use App\Domain\Social\Enums\ConversationStatus;
use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialConversation extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'social_channel_id', 'social_contact_id', 'status', 'assigned_to',
        'converted_order_id', 'converted_by', 'last_message_at', 'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SocialChannel::class, 'social_channel_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(SocialContact::class, 'social_contact_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SocialMessage::class)->orderBy('sent_at');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
