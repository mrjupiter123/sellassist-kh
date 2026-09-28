<?php

declare(strict_types=1);

namespace App\Http\Requests\OrderReturn;

use App\Domain\Payment\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->can('returns.create') === true
            && (! $this->filled('refund_amount') || $user->can('payments.refund'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'returned_at' => ['nullable', 'date', 'before_or_equal:now'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.restock' => ['required', 'boolean'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
            'refund_amount' => ['nullable', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'payment_id' => ['nullable', 'required_with:refund_amount', 'integer', 'exists:payments,id'],
            'refund_method' => ['nullable', 'required_with:refund_amount', Rule::enum(PaymentMethod::class)],
            'refund_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
