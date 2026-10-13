<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class CompleteDiscrepancyFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->active && $this->user()->canAny(['inventory.adjust', 'inventory.review']);
    }

    public function rules(): array
    {
        return ['outcome_note' => ['required', 'string', 'min:5', 'max:2000']];
    }
}
