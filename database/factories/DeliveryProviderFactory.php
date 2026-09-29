<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Delivery\Models\DeliveryProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeliveryProvider> */
class DeliveryProviderFactory extends Factory
{
    protected $model = DeliveryProvider::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Delivery',
            'code' => fake()->unique()->bothify('DLV-###'),
            'adapter' => 'manual',
            'integration_enabled' => false,
            'contact_phone' => fake()->optional()->phoneNumber(),
            'notes' => null,
            'active' => true,
        ];
    }
}
