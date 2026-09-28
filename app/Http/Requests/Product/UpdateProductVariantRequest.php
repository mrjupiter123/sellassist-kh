<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Domain\Product\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.update') === true;
    }

    public function rules(): array
    {
        /** @var ProductVariant $variant */
        $variant = $this->route('variant');

        return [
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($variant)],
            'color' => ['nullable', 'string', 'max:100', 'required_without:size'],
            'size' => ['nullable', 'string', 'max:100', 'required_without:color'],
            'price' => ['nullable', 'decimal:0,2', 'gte:0', 'max:9999999999999.99'],
            'cost' => ['nullable', 'decimal:0,2', 'gte:0', 'max:9999999999999.99'],
            'low_stock_threshold' => ['required', 'integer', 'gte:0'],
            'active' => ['required', 'boolean'],
        ];
    }
}
