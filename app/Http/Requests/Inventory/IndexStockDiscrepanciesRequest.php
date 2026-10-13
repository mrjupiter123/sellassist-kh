<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Domain\Inventory\Enums\DiscrepancyReviewFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStockDiscrepanciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.review') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'product' => ['nullable', 'uuid', Rule::exists('products', 'uuid')],
            'reviewed' => ['nullable', Rule::enum(DiscrepancyReviewFilter::class)],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('inventory.discrepancies.index');
    }
}
