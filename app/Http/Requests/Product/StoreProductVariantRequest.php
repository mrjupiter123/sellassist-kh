<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.create') === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'sku' => ['nullable', 'string', 'max:100', 'unique:product_variants,sku'],
            'color' => ['nullable', 'string', 'max:100', 'required_without:size'],
            'size' => ['nullable', 'string', 'max:100', 'required_without:color'],
            'price' => ['nullable', 'decimal:0,2', 'gte:0', 'max:9999999999999.99'],
            'cost' => ['nullable', 'decimal:0,2', 'gte:0', 'max:9999999999999.99'],
            'initial_stock' => ['sometimes', 'integer', 'gte:0'],
            'low_stock_threshold' => ['required', 'integer', 'gte:0'],
            'active' => ['required', 'boolean'],
        ];
    }
}
