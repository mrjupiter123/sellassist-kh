<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Models;

use App\Domain\Payment\Enums\Currency;
use App\Domain\Payment\Models\Payment;
use App\Models\User;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodRemittance extends Model
{
    use HasPublicUuid;

    protected $fillable = ['shipment_id', 'payment_id', 'amount', 'currency', 'reference', 'notes', 'remitted_at', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'currency' => Currency::class, 'remitted_at' => 'datetime'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
