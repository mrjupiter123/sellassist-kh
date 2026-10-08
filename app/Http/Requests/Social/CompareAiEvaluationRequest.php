<?php

declare(strict_types=1);

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompareAiEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('social.ai.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'baseline' => ['nullable', 'required_with:candidate', 'uuid', Rule::exists('social_ai_evaluation_runs', 'uuid')],
            'candidate' => ['nullable', 'required_with:baseline', 'uuid', 'different:baseline', Rule::exists('social_ai_evaluation_runs', 'uuid')],
        ];
    }
}
