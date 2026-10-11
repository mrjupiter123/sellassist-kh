<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Order\Models\Order;

final class PrepareRepeatOrder
{
    /**
     * @return array{customer_id: int, currency: string, items: list<array{product_id: int|string, product_variant_id: int|string, quantity: int, discount: string}>, warnings: list<string>}
     */
    public function execute(Order $source): array
    {
        $source->loadMissing(['items.product.variants', 'items.variant']);
        $items = [];
        $warnings = [];

        foreach ($source->items as $item) {
            $product = $item->product;
            $variant = $item->variant;

            if (! $product?->active
                || ($item->product_variant_id !== null && (! $variant?->active || $variant->product_id !== $product->id))
                || ($item->product_variant_id === null && $product->variants->contains('active', true))) {
                $warnings[] = "{$item->product_name} {$item->variant_name} is no longer available in the current catalog; choose a replacement.";

                continue;
            }

            $available = $variant?->stock_quantity ?? $product->stock_quantity;
            if ($available < $item->quantity) {
                $warnings[] = "{$item->product_name} {$item->variant_name} has only {$available} in stock; review the quantity before confirmation.";
            }

            $items[] = [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id ?? '',
                'quantity' => $item->quantity,
                'discount' => '0',
            ];
        }

        return [
            'customer_id' => $source->customer_id,
            'currency' => $source->currency->value,
            'items' => $items === [] ? [[
                'product_id' => '',
                'product_variant_id' => '',
                'quantity' => 1,
                'discount' => '0',
            ]] : $items,
            'warnings' => $warnings,
        ];
    }
}
