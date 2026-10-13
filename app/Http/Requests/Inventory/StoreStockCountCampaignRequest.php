<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockCountCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.adjust') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
            'assignments' => ['required', 'array', 'min:1', 'max:50'],
            'assignments.*.product' => ['required', 'uuid', Rule::exists('products', 'uuid')],
            'assignments.*.variant' => ['nullable', 'uuid', Rule::exists('product_variants', 'uuid')],
            'assignments.*.assignee' => ['required', 'uuid', Rule::exists('users', 'uuid')],
        ];
    }
}
