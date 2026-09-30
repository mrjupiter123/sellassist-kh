<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum SocialPlatform: string
{
    case FacebookMessenger = 'facebook_messenger';

    public function label(): string
    {
        return 'Facebook Messenger';
    }
}
