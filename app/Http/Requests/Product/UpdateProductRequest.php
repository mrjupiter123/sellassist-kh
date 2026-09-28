<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Domain\Product\Models\Product;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends StoreProductRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.update') === true;
    }

    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');
        $rules = parent::rules();
        $rules['sku'] = ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product->id)];
        unset($rules['initial_stock']);

        return $rules;
    }
}
