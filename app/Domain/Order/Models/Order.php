<?php

declare(strict_types=1);

namespace App\Domain\Order\Models;

use App\Domain\Customer\Enums\CustomerSource;
use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\OrderReturn\Models\OrderReturn;
use App\Domain\Payment\Enums\Currency;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Refund;
use App\Models\User;
use App\Support\HasPublicUuid;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(OrderFactory::class)]
class Order extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected $fillable = [
        'order_number',
        'customer_id',
        'source',
        'status',
        'payment_status',
        'currency',
        'subtotal',
        'discount',
        'delivery_fee',
        'total',
        'notes',
        'confirmed_at',
        'packed_at',
        'shipped_at',
        'completed_at',
        'cancelled_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'source' => CustomerSource::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'currency' => Currency::class,
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'packed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
