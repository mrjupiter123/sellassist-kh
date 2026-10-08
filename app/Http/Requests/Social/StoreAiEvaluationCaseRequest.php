<?php

declare(strict_types=1);

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAiEvaluationCaseRequest extends FormRequest
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
            'locale' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'],
            'messages_text' => ['required', 'string', 'max:10000'],
            'catalog_json' => ['required', 'json', 'max:50000'],
            'expected_result_json' => ['required', 'json', 'max:50000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['messages_text', 'catalog_json', 'expected_result_json'])) {
                return;
            }

            $catalog = json_decode($this->string('catalog_json')->toString(), true);
            $expected = json_decode($this->string('expected_result_json')->toString(), true);
            if ($this->messageLines() === []) {
                $validator->errors()->add('messages_text', 'Enter at least one non-empty synthetic message.');
            }
            if (! is_array($catalog) || ! array_is_list($catalog)) {
                $validator->errors()->add('catalog_json', 'The synthetic catalog must be a JSON array.');
            }
            if (! is_array($expected) || array_is_list($expected)) {
                $validator->errors()->add('expected_result_json', 'The expected result must be a JSON object.');
            }
        });
    }

    /** @return list<string> */
    public function messageLines(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\R/u', $this->string('messages_text')->toString()) ?: [],
        ), static fn (string $line): bool => $line !== ''));
    }

    /** @return list<array<string, mixed>> */
    public function catalog(): array
    {
        $decoded = json_decode($this->string('catalog_json')->toString(), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** @return array<string, mixed> */
    public function expectedResult(): array
    {
        $decoded = json_decode($this->string('expected_result_json')->toString(), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
