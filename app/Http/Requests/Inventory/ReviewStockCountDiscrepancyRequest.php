<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ReviewStockCountDiscrepancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.review') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'review_notes' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
