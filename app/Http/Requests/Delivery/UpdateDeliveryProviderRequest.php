<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use App\Domain\Delivery\Models\DeliveryProvider;
use Illuminate\Validation\Rule;

class UpdateDeliveryProviderRequest extends StoreDeliveryProviderRequest
{
    public function rules(): array
    {
        /** @var DeliveryProvider $provider */
        $provider = $this->route('provider');
        $rules = parent::rules();
        $rules['code'] = ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('delivery_providers', 'code')->ignore($provider)];

        return $rules;
    }
}
