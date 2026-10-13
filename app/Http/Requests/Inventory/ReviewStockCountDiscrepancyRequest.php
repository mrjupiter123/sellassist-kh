<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Domain\Inventory\Enums\StockDiscrepancyCause;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'root_cause' => ['required', Rule::enum(StockDiscrepancyCause::class)],
        ];
    }
}
