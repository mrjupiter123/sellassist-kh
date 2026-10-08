<?php

declare(strict_types=1);

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiExtractionProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('social.ai.manage') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'version' => [
                'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('social_ai_extraction_profiles', 'version')->where('name', $this->string('name')->trim()->toString()),
            ],
            'model' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'instructions' => ['required', 'string', 'max:10000'],
        ];
    }
}
