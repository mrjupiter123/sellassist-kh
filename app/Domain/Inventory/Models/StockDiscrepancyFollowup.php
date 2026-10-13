<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\DiscrepancyFollowupStatus;
use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockDiscrepancyFollowup extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'review_id', 'title', 'assigned_to', 'due_on', 'status',
        'outcome_note', 'created_by', 'completed_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'review_id' => 'integer',
            'assigned_to' => 'integer',
            'created_by' => 'integer',
            'completed_by' => 'integer',
            'due_on' => 'date',
            'status' => DiscrepancyFollowupStatus::class,
            'outcome_note' => 'encrypted',
            'completed_at' => 'datetime',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(StockCountDiscrepancyReview::class, 'review_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
