<?php

declare(strict_types=1);

namespace App\Http\Requests\Social;

use App\Domain\Payment\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertSocialConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('social.manage') === true
            && $this->user()?->can('orders.create') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'currency' => ['required', Rule::enum(Currency::class)],
            'discount' => ['nullable', 'decimal:0,2', 'gte:0', 'max:9999999999999.99'],
            'delivery_fee' => ['nullable', 'decimal:0,2', 'gte:0', 'max:9999999999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.discount' => ['nullable', 'decimal:0,2', 'gte:0', 'max:9999999999999.99'],
        ];
    }
}
