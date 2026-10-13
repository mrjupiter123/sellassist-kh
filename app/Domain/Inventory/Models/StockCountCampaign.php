<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCountCampaign extends Model
{
    use HasPublicUuid;

    protected $fillable = ['name', 'due_on', 'created_by'];

    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(StockCountTask::class);
    }
}
