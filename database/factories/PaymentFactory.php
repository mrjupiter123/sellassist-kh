<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\Currency;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'amount' => 5,
            'currency' => Currency::Usd,
            'payment_method' => PaymentMethod::Cash,
            'reference' => null,
            'notes' => null,
            'paid_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
