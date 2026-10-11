<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class RepeatOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.view') === true
            && $this->user()->can('orders.create');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
