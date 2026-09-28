<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => fake()->unique()->bothify('VAR-#####'),
            'color' => fake()->randomElement(['Black', 'White', 'Blue', 'Red']),
            'size' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'price' => null,
            'cost' => fake()->randomFloat(2, 2, 30),
            'stock_quantity' => 0,
            'low_stock_threshold' => 5,
            'active' => true,
        ];
    }
}
