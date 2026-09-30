<?php

declare(strict_types=1);

namespace App\Http\Requests\Social;

use App\Domain\Social\Enums\SocialPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('social.channels.manage') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['active' => $this->boolean('active')]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'external_id' => [
                'required', 'string', 'max:191',
                Rule::unique('social_channels')->where('platform', SocialPlatform::FacebookMessenger->value),
            ],
            'active' => ['required', 'boolean'],
        ];
    }
}
