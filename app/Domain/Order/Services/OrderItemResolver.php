<?php

declare(strict_types=1);

namespace App\Domain\Order\Services;

use App\Domain\Order\Exceptions\InvalidOrderTotalException;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;

final class OrderItemResolver
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{product: Product, variant: ProductVariant|null, quantity: int, unit_price: string, discount: mixed}>
     */
    public function resolve(array $items, bool $activeOnly = true): array
    {
        $resolved = [];

        foreach ($items as $item) {
            $product = Product::query()
                ->when($activeOnly, fn ($query) => $query->where('active', true))
                ->findOrFail($item['product_id']);
            $variant = null;

            if (! empty($item['product_variant_id'])) {
                $variant = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->when($activeOnly, fn ($query) => $query->where('active', true))
                    ->findOrFail($item['product_variant_id']);
            } elseif ($product->variants()->when($activeOnly, fn ($query) => $query->where('active', true))->exists()) {
                throw new InvalidOrderTotalException("Choose a variant for {$product->name}.");
            }

            $resolved[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => (int) $item['quantity'],
                'unit_price' => (string) ($variant?->price ?? $product->base_price),
                'discount' => $item['discount'] ?? '0',
            ];
        }

        return $resolved;
    }
}
