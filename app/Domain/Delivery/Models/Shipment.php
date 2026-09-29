<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\CodStatus;
use App\Domain\Delivery\Enums\IntegrationStatus;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\Currency;
use App\Models\User;
use App\Support\HasPublicUuid;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ShipmentFactory::class)]
class Shipment extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected $fillable = [
        'shipment_number', 'order_id', 'delivery_provider_id', 'tracking_number', 'status',
        'cod_amount', 'cod_status', 'currency', 'recipient_name', 'recipient_phone',
        'delivery_address', 'notes', 'ready_at', 'picked_up_at', 'delivered_at',
        'failed_at', 'returned_at', 'cancelled_at', 'created_by', 'external_id',
        'integration_status', 'integration_error', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'cod_status' => CodStatus::class,
            'currency' => Currency::class,
            'cod_amount' => 'decimal:2',
            'integration_status' => IntegrationStatus::class,
            'ready_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'returned_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(DeliveryProvider::class, 'delivery_provider_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class)->latest('occurred_at');
    }

    public function remittances(): HasMany
    {
        return $this->hasMany(CodRemittance::class)->latest('remitted_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function integrationLogs(): HasMany
    {
        return $this->hasMany(DeliveryIntegrationLog::class)->latest();
    }
}
