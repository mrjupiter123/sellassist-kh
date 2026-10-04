<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Social\Enums\OrderExtractionStatus;
use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialOrderExtraction extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'social_conversation_id', 'status', 'provider', 'model', 'input_hash', 'message_count',
        'source_message_ids', 'customer_name', 'phone', 'address', 'province', 'district',
        'commune', 'notes', 'overall_confidence', 'provider_response_id', 'result_payload',
        'error', 'requested_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderExtractionStatus::class,
            'message_count' => 'integer',
            'source_message_ids' => 'encrypted:array',
            'customer_name' => 'encrypted',
            'phone' => 'encrypted',
            'address' => 'encrypted',
            'province' => 'encrypted',
            'district' => 'encrypted',
            'commune' => 'encrypted',
            'notes' => 'encrypted',
            'overall_confidence' => 'decimal:4',
            'result_payload' => 'encrypted:array',
            'processed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SocialConversation::class, 'social_conversation_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SocialOrderExtractionItem::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
