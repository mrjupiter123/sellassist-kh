<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Domain\Customer\Enums\CustomerSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.create') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'facebook_name' => ['nullable', 'string', 'max:255'],
            'facebook_profile_url' => ['nullable', 'url', 'max:2048'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone_secondary' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'province' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'commune' => ['nullable', 'string', 'max:255'],
            'source' => ['required', Rule::enum(CustomerSource::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
