<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Exceptions\InvalidStockCountException;
use App\Domain\Inventory\Models\StockCountCampaign;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateStockCountCampaign
{
    public function __construct(private readonly CreateStockCountTask $createTask) {}

    /** @param list<array{product: string, variant?: string|null, assignee: string}> $assignments */
    public function execute(string $name, ?string $dueOn, array $assignments, User $creator): StockCountCampaign
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 150 || ! $creator->active || ! $creator->can('inventory.adjust')) {
            throw new InvalidStockCountException('A campaign needs a valid name and an active inventory user.');
        }

        if ($assignments === [] || count($assignments) > 50) {
            throw new InvalidStockCountException('A campaign must contain between 1 and 50 assignments.');
        }

        return DB::transaction(function () use ($name, $dueOn, $assignments, $creator): StockCountCampaign {
            $campaign = StockCountCampaign::query()->create([
                'name' => $name,
                'due_on' => $dueOn,
                'created_by' => $creator->id,
            ]);
            $seen = [];

            foreach ($assignments as $assignment) {
                $product = Product::query()->where('uuid', $assignment['product'])->firstOrFail();
                $variant = ! empty($assignment['variant'])
                    ? ProductVariant::query()->where('uuid', $assignment['variant'])->firstOrFail()
                    : null;
                $itemKey = $product->id.':'.($variant?->id ?? 'base');

                if (isset($seen[$itemKey])) {
                    throw new InvalidStockCountException('Each product or variant may appear only once in a campaign.');
                }

                $seen[$itemKey] = true;
                $assignee = User::query()->where('uuid', $assignment['assignee'])->firstOrFail();
                $this->createTask->execute($product, $variant, $assignee, $creator, $dueOn, campaign: $campaign);
            }

            return $campaign;
        });
    }
}
