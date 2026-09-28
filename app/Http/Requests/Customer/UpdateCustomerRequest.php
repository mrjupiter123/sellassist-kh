<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

class UpdateCustomerRequest extends StoreCustomerRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.update') === true;
    }
}
