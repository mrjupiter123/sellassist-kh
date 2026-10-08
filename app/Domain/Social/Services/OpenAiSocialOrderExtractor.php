<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Product\Models\Product;
use App\Domain\Social\Data\SocialOrderExtractionResult;
use App\Domain\Social\Exceptions\SocialOrderExtractionException;
use App\Domain\Social\Models\SocialMessage;
use App\Domain\Social\Models\SocialOrderExtraction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

final class OpenAiSocialOrderExtractor
{
    public function __construct(private readonly AiExtractionPrompt $prompt) {}

    public function extract(SocialOrderExtraction $extraction): SocialOrderExtractionResult
    {
        $apiKey = (string) config('social.ai.api_key');
        if ($apiKey === '') {
            throw new SocialOrderExtractionException('AI order extraction is not configured.');
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('social.ai.base_url'), '/'))
                ->withToken($apiKey)
                ->acceptJson()
                ->timeout(45)
                ->post('/responses', [
                    'model' => $extraction->model,
                    'store' => false,
                    'instructions' => $this->prompt->effectiveInstructions($extraction->profile),
                    'input' => [[
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => json_encode($this->context($extraction), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        ]],
                    ]],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'social_order_suggestion',
                            'strict' => true,
                            'schema' => $this->schema(),
                        ],
                    ],
                    'max_output_tokens' => 1800,
                ]);
        } catch (ConnectionException) {
            throw new SocialOrderExtractionException('The AI provider could not be reached. Try again later.');
        } catch (Throwable $exception) {
            report($exception);
            throw new SocialOrderExtractionException('The AI extraction request could not be prepared.');
        }

        if (! $response->successful()) {
            throw new SocialOrderExtractionException('The AI provider rejected the extraction request.');
        }

        $body = $response->json();
        $output = collect($body['output'] ?? [])
            ->where('type', 'message')
            ->flatMap(fn (array $message): array => $message['content'] ?? [])
            ->firstWhere('type', 'output_text');

        if (! is_array($output) || ! is_string($output['text'] ?? null)) {
            throw new SocialOrderExtractionException('The AI provider returned no structured suggestion.');
        }

        try {
            $payload = json_decode($output['text'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SocialOrderExtractionException('The AI provider returned an invalid structured suggestion.');
        }

        if (! is_array($payload)) {
            throw new SocialOrderExtractionException('The AI provider returned an invalid suggestion.');
        }

        return new SocialOrderExtractionResult(
            responseId: $this->limitedString($body['id'] ?? null, 191) ?? '',
            customerName: $this->limitedString($payload['customer_name'] ?? null, 255),
            phone: $this->limitedString($payload['phone'] ?? null, 100),
            address: $this->limitedString($payload['address'] ?? null, 1000),
            province: $this->limitedString($payload['province'] ?? null, 255),
            district: $this->limitedString($payload['district'] ?? null, 255),
            commune: $this->limitedString($payload['commune'] ?? null, 255),
            notes: $this->limitedString($payload['notes'] ?? null, 2000),
            overallConfidence: $this->confidence($payload['overall_confidence'] ?? 0),
            items: $this->items($payload['items'] ?? []),
            payload: $payload,
            inputTokens: $this->tokenCount($body['usage']['input_tokens'] ?? null),
            outputTokens: $this->tokenCount($body['usage']['output_tokens'] ?? null),
            totalTokens: $this->tokenCount($body['usage']['total_tokens'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    private function context(SocialOrderExtraction $extraction): array
    {
        $messageIds = array_map('intval', $extraction->source_message_ids);
        $messages = SocialMessage::query()
            ->whereIn('id', $messageIds)
            ->orderBy('sent_at')
            ->get(['id', 'external_id', 'body', 'sent_at'])
            ->map(fn (SocialMessage $message): array => [
                'message_ref' => $message->external_id ?: (string) $message->id,
                'text' => mb_substr((string) $message->body, 0, 2500),
                'sent_at' => $message->sent_at?->toIso8601String(),
            ])->values()->all();

        $catalog = Product::query()
            ->where('active', true)
            ->with(['variants' => fn ($query) => $query->where('active', true)])
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'uuid', 'name', 'sku'])
            ->map(fn (Product $product): array => [
                'product_ref' => $product->uuid,
                'name' => $product->name,
                'sku' => $product->sku,
                'variants' => $product->variants->map(fn ($variant): array => [
                    'variant_ref' => $variant->uuid,
                    'name' => $variant->display_name,
                    'sku' => $variant->sku,
                ])->values()->all(),
            ])->values()->all();

        return [
            'customer_messages' => $messages,
            'active_catalog' => $catalog,
        ];
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        $nullableString = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['customer_name', 'phone', 'address', 'province', 'district', 'commune', 'notes', 'overall_confidence', 'items'],
            'properties' => [
                'customer_name' => $nullableString,
                'phone' => $nullableString,
                'address' => $nullableString,
                'province' => $nullableString,
                'district' => $nullableString,
                'commune' => $nullableString,
                'notes' => $nullableString,
                'overall_confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'items' => [
                    'type' => 'array',
                    'maxItems' => 50,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['product_ref', 'variant_ref', 'product_query', 'variant_query', 'quantity', 'confidence'],
                        'properties' => [
                            'product_ref' => $nullableString,
                            'variant_ref' => $nullableString,
                            'product_query' => ['type' => 'string'],
                            'variant_query' => $nullableString,
                            'quantity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10000],
                            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return list<array{product_ref: ?string, variant_ref: ?string, product_query: string, variant_query: ?string, quantity: int, confidence: float}> */
    private function items(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)->take(50)->filter(fn ($item) => is_array($item))->map(fn (array $item): array => [
            'product_ref' => $this->limitedString($item['product_ref'] ?? null, 36),
            'variant_ref' => $this->limitedString($item['variant_ref'] ?? null, 36),
            'product_query' => $this->limitedString($item['product_query'] ?? '', 255) ?? '',
            'variant_query' => $this->limitedString($item['variant_query'] ?? null, 255),
            'quantity' => max(1, min(10000, (int) ($item['quantity'] ?? 1))),
            'confidence' => $this->confidence($item['confidence'] ?? 0),
        ])->values()->all();
    }

    private function limitedString(mixed $value, int $length): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, $length);
    }

    private function confidence(mixed $value): float
    {
        return max(0, min(1, is_numeric($value) ? (float) $value : 0));
    }

    private function tokenCount(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }
}
