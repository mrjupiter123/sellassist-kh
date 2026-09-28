<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Order\Models\OrderItem;
use App\Support\HasPublicUuid;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ProductVariantFactory::class)]
class ProductVariant extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected $fillable = [
        'product_id',
        'sku',
        'color',
        'size',
        'price',
        'cost',
        'low_stock_threshold',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getDisplayNameAttribute(): string
    {
        $parts = array_filter([$this->color, $this->size]);

        return $parts === [] ? 'Default' : implode(' / ', $parts);
    }

    public function sellingPrice(): string
    {
        return $this->price ?? $this->product->base_price;
    }
}
