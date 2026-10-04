<?php

declare(strict_types=1);

namespace App\Domain\Social\Data;

use Carbon\CarbonImmutable;

final readonly class OutboundSocialMessageResult
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $externalId,
        public CarbonImmutable $sentAt,
        public array $payload,
    ) {}
}
