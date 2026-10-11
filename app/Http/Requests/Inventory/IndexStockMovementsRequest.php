<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Domain\Inventory\Enums\StockMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStockMovementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.view') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'product' => ['nullable', 'uuid', Rule::exists('products', 'uuid')],
            'variant' => ['nullable', 'uuid', Rule::exists('product_variants', 'uuid')],
            'type' => ['nullable', Rule::enum(StockMovementType::class)],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('inventory.index');
    }
}
