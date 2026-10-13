<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Domain\Inventory\Enums\DiscrepancyTrendWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStockDiscrepancyTrendsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.review') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'window' => ['nullable', Rule::enum(DiscrepancyTrendWindow::class)],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('inventory.discrepancies.trends');
    }
}
