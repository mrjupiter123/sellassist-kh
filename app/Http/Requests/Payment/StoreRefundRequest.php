<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use App\Domain\Payment\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payments.refund') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'refund_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'refunded_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
