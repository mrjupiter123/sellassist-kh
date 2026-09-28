<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.create') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:255'],
            'base_price' => ['required', 'decimal:0,2', 'gte:0', 'max:9999999999999.99'],
            'initial_stock' => ['sometimes', 'integer', 'gte:0'],
            'low_stock_threshold' => ['required', 'integer', 'gte:0'],
            'active' => ['required', 'boolean'],
        ];
    }
}
