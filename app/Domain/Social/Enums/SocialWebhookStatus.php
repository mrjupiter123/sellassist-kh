<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum SocialWebhookStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Failed = 'failed';
}
