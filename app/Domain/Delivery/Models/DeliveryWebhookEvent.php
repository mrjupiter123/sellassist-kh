<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Models;

use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryWebhookEvent extends Model
{
    use HasPublicUuid;

    protected $fillable = ['delivery_provider_id', 'event_id', 'status', 'payload', 'error', 'received_at', 'processed_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(DeliveryProvider::class, 'delivery_provider_id');
    }
}
