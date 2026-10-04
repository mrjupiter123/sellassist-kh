<?php

declare(strict_types=1);

namespace App\Http\Requests\Social;

use App\Domain\Social\Enums\ConversationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeSocialConversationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('social.manage') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                ConversationStatus::Open->value,
                ConversationStatus::Archived->value,
            ])],
        ];
    }
}
