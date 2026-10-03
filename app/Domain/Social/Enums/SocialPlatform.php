<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum SocialPlatform: string
{
    case FacebookMessenger = 'facebook_messenger';
    case Telegram = 'telegram';

    public function label(): string
    {
        return match ($this) {
            self::FacebookMessenger => 'Facebook Messenger',
            self::Telegram => 'Telegram',
        };
    }
}
