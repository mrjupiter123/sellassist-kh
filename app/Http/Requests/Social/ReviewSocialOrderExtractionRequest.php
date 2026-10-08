<?php

declare(strict_types=1);

namespace App\Http\Requests\Social;

use App\Domain\Social\Enums\OrderExtractionReviewVerdict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewSocialOrderExtractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('social.extract') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'verdict' => ['required', Rule::enum(OrderExtractionReviewVerdict::class)],
            'customer_fields_correct' => ['nullable', 'boolean'],
            'item_matches_correct' => ['nullable', 'boolean'],
            'quantities_correct' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
