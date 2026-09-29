<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Models;

use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryIntegrationLog extends Model
{
    use HasPublicUuid;

    protected $fillable = ['shipment_id', 'operation', 'status', 'response_code', 'request_payload', 'response_payload', 'error'];

    protected function casts(): array
    {
        return ['request_payload' => 'encrypted:array', 'response_payload' => 'encrypted:array'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
