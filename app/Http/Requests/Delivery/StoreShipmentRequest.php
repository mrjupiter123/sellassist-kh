<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delivery.create') === true;
    }

    public function rules(): array
    {
        return [
            'delivery_provider_id' => ['required', 'integer', Rule::exists('delivery_providers', 'id')->where('active', true)],
            'tracking_number' => [
                'nullable', 'string', 'max:255',
                Rule::unique('shipments', 'tracking_number')->where('delivery_provider_id', $this->input('delivery_provider_id')),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
