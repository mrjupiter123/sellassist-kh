<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Enums\SocialWebhookStatus;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class SocialWebhookEvent extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'platform', 'event_id', 'status', 'payload', 'processed_messages',
        'error', 'received_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'status' => SocialWebhookStatus::class,
            'payload' => 'encrypted:array',
            'processed_messages' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
