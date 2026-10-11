<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Domain\Customer\Enums\CustomerSource;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Payment\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.view') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'source' => ['nullable', Rule::enum(CustomerSource::class)],
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('orders.index');
    }
}
