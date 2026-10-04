<?php

declare(strict_types=1);

namespace App\Domain\Social\Data;

final readonly class SocialOrderExtractionResult
{
    /**
     * @param  list<array{product_ref: ?string, variant_ref: ?string, product_query: string, variant_query: ?string, quantity: int, confidence: float}>  $items
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $responseId,
        public ?string $customerName,
        public ?string $phone,
        public ?string $address,
        public ?string $province,
        public ?string $district,
        public ?string $commune,
        public ?string $notes,
        public float $overallConfidence,
        public array $items,
        public array $payload,
    ) {}
}
