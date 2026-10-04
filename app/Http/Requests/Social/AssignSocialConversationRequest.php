<?php

declare(strict_types=1);

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;

class AssignSocialConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('social.manage') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
