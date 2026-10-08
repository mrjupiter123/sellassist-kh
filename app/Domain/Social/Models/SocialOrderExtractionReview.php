<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Social\Enums\OrderExtractionReviewVerdict;
use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialOrderExtractionReview extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'social_order_extraction_id', 'verdict', 'customer_fields_correct',
        'item_matches_correct', 'quantities_correct', 'notes', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'verdict' => OrderExtractionReviewVerdict::class,
            'customer_fields_correct' => 'boolean',
            'item_matches_correct' => 'boolean',
            'quantities_correct' => 'boolean',
            'notes' => 'encrypted',
            'reviewed_at' => 'datetime',
        ];
    }

    public function extraction(): BelongsTo
    {
        return $this->belongsTo(SocialOrderExtraction::class, 'social_order_extraction_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
