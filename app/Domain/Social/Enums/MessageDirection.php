<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum MessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
