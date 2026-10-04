<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialOrderExtractionItem extends Model
{
    protected $fillable = [
        'social_order_extraction_id', 'product_id', 'product_variant_id', 'product_query',
        'variant_query', 'quantity', 'confidence',
    ];

    protected function casts(): array
    {
        return [
            'product_query' => 'encrypted',
            'variant_query' => 'encrypted',
            'quantity' => 'integer',
            'confidence' => 'decimal:4',
        ];
    }

    public function extraction(): BelongsTo
    {
        return $this->belongsTo(SocialOrderExtraction::class, 'social_order_extraction_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
