<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountDiscrepancyReview extends Model
{
    use HasPublicUuid;

    protected $fillable = ['stock_count_id', 'reviewed_by', 'review_notes', 'reviewed_at'];

    protected function casts(): array
    {
        return [
            'stock_count_id' => 'integer',
            'reviewed_by' => 'integer',
            'review_notes' => 'encrypted',
            'reviewed_at' => 'datetime',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
