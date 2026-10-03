<?php

declare(strict_types=1);

namespace App\Domain\Social\Data;

use App\Domain\Social\Enums\MessageType;
use App\Domain\Social\Enums\SocialPlatform;
use Carbon\CarbonImmutable;

final readonly class InboundSocialMessage
{
    /** @param list<array<string, mixed>> $attachments
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public SocialPlatform $platform,
        public string $channelExternalId,
        public string $contactExternalId,
        public string $messageExternalId,
        public ?string $contactDisplayName,
        public MessageType $type,
        public ?string $body,
        public array $attachments,
        public CarbonImmutable $sentAt,
        public array $rawPayload,
    ) {}
}
