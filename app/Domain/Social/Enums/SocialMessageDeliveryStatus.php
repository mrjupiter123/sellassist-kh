<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum SocialMessageDeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }
}
