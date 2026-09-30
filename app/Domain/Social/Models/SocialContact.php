<?php

declare(strict_types=1);

namespace App\Domain\Social\Models;

use App\Domain\Customer\Models\Customer;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialContact extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'social_channel_id', 'customer_id', 'suggested_customer_id', 'external_id',
        'display_name', 'first_seen_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SocialChannel::class, 'social_channel_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function suggestedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'suggested_customer_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(SocialConversation::class);
    }
}
