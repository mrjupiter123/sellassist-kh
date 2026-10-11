<?php

declare(strict_types=1);

namespace App\Domain\Operations\Models;

use Illuminate\Database\Eloquent\Model;

class OperationsHeartbeat extends Model
{
    protected $fillable = ['name', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
