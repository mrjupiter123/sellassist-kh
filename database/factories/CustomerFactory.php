<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Customer\Enums\CustomerSource;
use App\Domain\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'facebook_name' => fake()->optional()->userName(),
            'facebook_profile_url' => null,
            'phone' => fake()->optional(0.85)->numerify('0## ### ###'),
            'phone_secondary' => null,
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->streetAddress(),
            'province' => fake()->randomElement(['Phnom Penh', 'Kandal', 'Siem Reap', 'Battambang']),
            'district' => null,
            'commune' => null,
            'source' => fake()->randomElement(CustomerSource::cases())->value,
            'notes' => null,
        ];
    }
}
