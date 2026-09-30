<?php

declare(strict_types=1);

namespace App\Domain\Social\Data;

use App\Domain\Social\Enums\MessageType;
use Carbon\CarbonImmutable;

final readonly class InboundSocialMessage
{
    /** @param list<array<string, mixed>> $attachments
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $channelExternalId,
        public string $contactExternalId,
        public string $messageExternalId,
        public MessageType $type,
        public ?string $body,
        public array $attachments,
        public CarbonImmutable $sentAt,
        public array $rawPayload,
    ) {}
}
