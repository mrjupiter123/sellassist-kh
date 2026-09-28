<?php

declare(strict_types=1);

namespace App\Domain\Customer\Models;

use App\Domain\Customer\Enums\CustomerSource;
use App\Domain\Order\Models\Order;
use App\Support\HasPublicUuid;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(CustomerFactory::class)]
class Customer extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected $fillable = [
        'name',
        'facebook_name',
        'facebook_profile_url',
        'phone',
        'phone_secondary',
        'email',
        'address',
        'province',
        'district',
        'commune',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return ['source' => CustomerSource::class];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
