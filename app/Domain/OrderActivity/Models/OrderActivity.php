<?php

declare(strict_types=1);

namespace App\Domain\OrderActivity\Models;

use App\Domain\Order\Models\Order;
use App\Domain\OrderActivity\Enums\OrderActivityType;
use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderActivity extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'order_id',
        'type',
        'description',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderActivityType::class,
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
