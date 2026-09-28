<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Domain\Order\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->input('status') === OrderStatus::Cancelled->value
            ? 'orders.cancel'
            : 'orders.update';

        return $this->user()?->can($permission) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(OrderStatus::class)]];
    }
}
