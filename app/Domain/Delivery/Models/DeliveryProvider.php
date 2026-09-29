<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\DeliveryAdapter;
use App\Support\HasPublicUuid;
use Database\Factories\DeliveryProviderFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(DeliveryProviderFactory::class)]
class DeliveryProvider extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected $fillable = ['name', 'code', 'adapter', 'integration_enabled', 'contact_phone', 'notes', 'active'];

    protected function casts(): array
    {
        return [
            'adapter' => DeliveryAdapter::class,
            'integration_enabled' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(DeliveryWebhookEvent::class);
    }
}
