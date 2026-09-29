<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Delivery\Models\CodRemittance;
use App\Domain\Delivery\Models\Shipment;
use App\Domain\Delivery\Models\ShipmentStatusHistory;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Order\Models\Order;
use App\Domain\OrderActivity\Models\OrderActivity;
use App\Domain\OrderReturn\Models\OrderReturn;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Refund;
use App\Support\HasPublicUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicUuid, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'created_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'created_by');
    }

    public function orderReturns(): HasMany
    {
        return $this->hasMany(OrderReturn::class, 'created_by');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'created_by');
    }

    public function orderActivities(): HasMany
    {
        return $this->hasMany(OrderActivity::class, 'created_by');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'created_by');
    }

    public function shipmentStatusHistories(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class, 'created_by');
    }

    public function codRemittances(): HasMany
    {
        return $this->hasMany(CodRemittance::class, 'created_by');
    }
}
