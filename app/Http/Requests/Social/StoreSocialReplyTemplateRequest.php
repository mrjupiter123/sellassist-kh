<?php

declare(strict_types=1);

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialReplyTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('social.manage') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->input('title')) ? trim($this->input('title')) : $this->input('title'),
            'body' => is_string($this->input('body')) ? trim($this->input('body')) : $this->input('body'),
            'active' => $this->boolean('active'),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:191', Rule::unique('social_reply_templates', 'title')],
            'body' => ['required', 'string', 'max:2000'],
            'active' => ['required', 'boolean'],
        ];
    }
}
