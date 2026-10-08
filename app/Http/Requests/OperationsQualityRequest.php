<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperationsQualityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operations.view') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'integer', Rule::in([7, 30, 90])],
        ];
    }
}
