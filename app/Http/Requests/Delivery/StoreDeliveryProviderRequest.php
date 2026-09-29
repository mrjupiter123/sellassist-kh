<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use App\Domain\Delivery\Enums\DeliveryAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delivery.providers.manage') === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:delivery_providers,code'],
            'adapter' => ['required', Rule::enum(DeliveryAdapter::class)],
            'integration_enabled' => ['required', 'boolean'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['required', 'boolean'],
        ];
    }
}
