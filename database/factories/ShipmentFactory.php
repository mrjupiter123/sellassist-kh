<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Delivery\Enums\CodStatus;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Models\DeliveryProvider;
use App\Domain\Delivery\Models\Shipment;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'shipment_number' => 'SHP-'.now()->format('Ymd').'-'.fake()->unique()->numerify('#####'),
            'order_id' => Order::factory()->state(['status' => OrderStatus::Confirmed]),
            'delivery_provider_id' => DeliveryProvider::factory(),
            'tracking_number' => fake()->unique()->bothify('TRK-########'),
            'integration_status' => 'manual',
            'status' => ShipmentStatus::Pending,
            'cod_amount' => '10.00',
            'cod_status' => CodStatus::PendingCollection,
            'currency' => 'USD',
            'recipient_name' => fake()->name(),
            'recipient_phone' => fake()->phoneNumber(),
            'delivery_address' => fake()->address(),
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
