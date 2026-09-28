<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Order\Models\OrderItem;
use App\Support\HasPublicUuid;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ProductFactory::class)]
class Product extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected $fillable = [
        'name',
        'sku',
        'description',
        'category',
        'base_price',
        'low_stock_threshold',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
