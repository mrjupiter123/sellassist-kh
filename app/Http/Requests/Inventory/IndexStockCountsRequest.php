<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Domain\Inventory\Enums\StockCountDifferenceFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStockCountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.adjust') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'product' => ['nullable', 'uuid', Rule::exists('products', 'uuid')],
            'variant' => ['nullable', 'uuid', Rule::exists('product_variants', 'uuid')],
            'difference' => ['nullable', Rule::enum(StockCountDifferenceFilter::class)],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('inventory.stock-counts.index');
    }
}
