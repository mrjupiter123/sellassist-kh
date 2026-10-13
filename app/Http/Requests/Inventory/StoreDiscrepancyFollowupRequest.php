<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiscrepancyFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->active && $this->user()->can('inventory.review');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'assignee' => ['required', 'uuid', Rule::exists('users', 'uuid')->where('active', true)],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
