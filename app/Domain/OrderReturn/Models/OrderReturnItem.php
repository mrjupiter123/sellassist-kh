<?php

declare(strict_types=1);

namespace App\Domain\OrderReturn\Models;

use App\Domain\Order\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnItem extends Model
{
    protected $fillable = [
        'order_item_id',
        'quantity',
        'restock',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'restock' => 'boolean',
        ];
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
