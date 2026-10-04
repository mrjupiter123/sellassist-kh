<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialReplyTemplate extends Model
{
    use HasPublicUuid;

    protected $fillable = ['title', 'body', 'active', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'active' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
