<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockCountTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.adjust') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'product' => ['required', 'uuid', Rule::exists('products', 'uuid')],
            'variant' => ['nullable', 'uuid', Rule::exists('product_variants', 'uuid')],
            'assignee' => ['required', 'uuid', Rule::exists('users', 'uuid')],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
