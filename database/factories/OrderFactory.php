<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Customer\Enums\CustomerSource;
use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $total = fake()->randomFloat(2, 10, 150);

        return [
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->numerify('#####'),
            'customer_id' => Customer::factory(),
            'source' => CustomerSource::Manual,
            'status' => OrderStatus::New,
            'payment_status' => PaymentStatus::Unpaid,
            'currency' => 'USD',
            'subtotal' => $total,
            'discount' => 0,
            'delivery_fee' => 0,
            'total' => $total,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
