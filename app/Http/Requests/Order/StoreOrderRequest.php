<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Domain\Customer\Enums\CustomerSource;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Payment\Enums\Currency;
use App\Domain\Payment\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->can('orders.create') === true
            && (! $this->filled('payment_amount') || $user->can('payments.create'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'source' => ['required', Rule::enum(CustomerSource::class)],
            'status' => ['required', Rule::in([OrderStatus::Draft->value, OrderStatus::New->value])],
            'currency' => ['required', Rule::enum(Currency::class)],
            'discount' => ['nullable', 'decimal:0,2', 'gte:0', 'max:9999999999999.99'],
            'delivery_fee' => ['nullable', 'decimal:0,2', 'gte:0', 'max:9999999999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.discount' => ['nullable', 'decimal:0,2', 'gte:0', 'max:9999999999999.99'],
            'payment_amount' => ['nullable', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'payment_method' => ['nullable', 'required_with:payment_amount', Rule::enum(PaymentMethod::class)],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
