<?php

declare(strict_types=1);

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiEvaluationDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('social.ai.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'version' => [
                'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/',
                Rule::unique('social_ai_evaluation_datasets', 'version')
                    ->where(fn ($query) => $query->where('name', $this->string('name')->trim()->toString())),
            ],
            'release_notes' => ['required', 'string', 'max:2000'],
            'case_ids' => ['required', 'array', 'min:1'],
            'case_ids.*' => ['required', 'integer', 'distinct', Rule::exists('social_ai_evaluation_cases', 'id')->where('active', true)],
        ];
    }
}
