<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Domain\Inventory\Enums\DiscrepancyFollowupStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDiscrepancyFollowupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->active && $this->user()->canAny(['inventory.adjust', 'inventory.review']);
    }

    public function rules(): array
    {
        return ['status' => ['nullable', Rule::enum(DiscrepancyFollowupStatus::class)]];
    }

    protected function getRedirectUrl(): string
    {
        return route('inventory.followups.index');
    }
}
