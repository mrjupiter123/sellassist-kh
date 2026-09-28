<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

class UpdateOrderRequest extends StoreOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.update') === true;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['payment_amount'], $rules['payment_method'], $rules['payment_reference']);

        return $rules;
    }
}
