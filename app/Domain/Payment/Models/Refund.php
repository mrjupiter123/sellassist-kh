<?php

declare(strict_types=1);

namespace App\Domain\Payment\Models;

use App\Domain\Order\Models\Order;
use App\Domain\OrderReturn\Models\OrderReturn;
use App\Domain\Payment\Enums\Currency;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'order_id',
        'payment_id',
        'order_return_id',
        'amount',
        'currency',
        'refund_method',
        'reference',
        'notes',
        'refunded_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'currency' => Currency::class,
            'refund_method' => PaymentMethod::class,
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
