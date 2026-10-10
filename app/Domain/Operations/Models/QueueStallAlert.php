<?php

declare(strict_types=1);

namespace App\Domain\Operations\Models;

use App\Domain\Operations\Enums\QueueHealthStatus;
use Illuminate\Database\Eloquent\Model;

class QueueStallAlert extends Model
{
    protected $fillable = [
        'queue', 'status', 'stale_jobs', 'stalled_since',
        'last_checked_at', 'last_notified_at', 'recovered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QueueHealthStatus::class,
            'stalled_since' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }
}
