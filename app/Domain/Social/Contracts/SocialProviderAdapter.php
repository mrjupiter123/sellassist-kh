<?php

declare(strict_types=1);

namespace App\Domain\Social\Contracts;

use App\Domain\Social\Data\InboundSocialMessage;
use App\Domain\Social\Models\SocialChannel;

interface SocialProviderAdapter
{
    public function signatureIsValid(string $rawPayload, ?string $signature): bool;

    /** @param array<string, mixed> $payload
     * @return list<InboundSocialMessage>
     */
    public function messages(array $payload, ?SocialChannel $channel = null): array;
}
