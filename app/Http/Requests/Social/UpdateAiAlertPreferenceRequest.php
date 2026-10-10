<?php

declare(strict_types=1);

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiAlertPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('social.ai.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['email_enabled' => ['required', 'boolean']];
    }
}
