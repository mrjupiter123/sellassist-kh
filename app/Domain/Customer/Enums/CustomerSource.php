<?php

declare(strict_types=1);

namespace App\Domain\Customer\Enums;

enum CustomerSource: string
{
    case Facebook = 'facebook';
    case Messenger = 'messenger';
    case FacebookLive = 'facebook_live';
    case Telegram = 'telegram';
    case Instagram = 'instagram';
    case Manual = 'manual';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FacebookLive => 'Facebook Live',
            default => str($this->value)->headline()->toString(),
        };
    }
}
