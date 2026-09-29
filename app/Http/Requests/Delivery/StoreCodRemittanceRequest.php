<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class StoreCodRemittanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delivery.cod.reconcile') === true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'remitted_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
