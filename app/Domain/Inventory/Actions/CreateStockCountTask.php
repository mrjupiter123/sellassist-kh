<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Exceptions\InvalidStockCountException;
use App\Domain\Inventory\Models\StockCountTask;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Models\User;

class CreateStockCountTask
{
    public function execute(
        Product $product,
        ?ProductVariant $variant,
        User $assignee,
        User $creator,
        ?string $dueOn = null,
        ?string $instructions = null,
    ): StockCountTask {
        if (! $product->active || ($variant !== null && (! $variant->active || $variant->product_id !== $product->id))) {
            throw new InvalidStockCountException('Choose an active product and one of its active variants.');
        }

        if ($variant === null && $product->variants()->exists()) {
            throw new InvalidStockCountException('Choose a variant for a product with variants.');
        }

        if (! $assignee->active || ! $assignee->can('inventory.adjust')) {
            throw new InvalidStockCountException('Assign the count to an active user with inventory adjustment access.');
        }

        return StockCountTask::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'assigned_to' => $assignee->id,
            'created_by' => $creator->id,
            'due_on' => $dueOn,
            'instructions' => $instructions,
        ]);
    }
}
