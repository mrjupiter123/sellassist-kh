<?php

declare(strict_types=1);

namespace App\Http\Requests\Social;

use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Models\SocialChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSocialChannelRequest extends FormRequest
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
        /** @var SocialChannel $channel */
        $channel = $this->route('channel');

        $externalIdRules = ['required', 'string', 'max:191'];
        if ($channel->platform === SocialPlatform::Telegram) {
            $externalIdRules[] = Rule::in([$channel->external_id]);
        } else {
            $externalIdRules[] = Rule::unique('social_channels')
                ->where('platform', $channel->platform->value)
                ->ignore($channel->id);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'external_id' => $externalIdRules,
            'active' => ['required', 'boolean'],
        ];
    }
}
