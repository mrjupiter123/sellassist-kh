<?php

declare(strict_types=1);

namespace App\Domain\OrderReturn\Models;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\Refund;
use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturn extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'return_number',
        'order_id',
        'reason',
        'notes',
        'returned_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['returned_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
