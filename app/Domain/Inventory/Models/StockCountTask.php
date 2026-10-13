<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountTask extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'assigned_to',
        'created_by',
        'due_on',
        'instructions',
        'stock_count_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'assigned_to' => 'integer',
            'created_by' => 'integer',
            'stock_count_id' => 'integer',
            'due_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }
}
