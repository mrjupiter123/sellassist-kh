<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCount extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'product_id',
        'product_variant_id',
        'expected_quantity',
        'counted_quantity',
        'difference',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'expected_quantity' => 'integer',
            'counted_quantity' => 'integer',
            'difference' => 'integer',
            'created_by' => 'integer',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
