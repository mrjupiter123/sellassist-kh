<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Product\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('PRD-####'),
            'description' => fake()->optional()->sentence(),
            'category' => fake()->randomElement(['Clothing', 'Beauty', 'Home', 'Accessories']),
            'base_price' => fake()->randomFloat(2, 5, 80),
            'stock_quantity' => 0,
            'low_stock_threshold' => 5,
            'active' => true,
        ];
    }
}
