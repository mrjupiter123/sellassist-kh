<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class CompleteStockCountTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.adjust') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'observed_quantity' => ['required', 'integer', 'min:0'],
            'counted_quantity' => ['required', 'integer', 'min:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
