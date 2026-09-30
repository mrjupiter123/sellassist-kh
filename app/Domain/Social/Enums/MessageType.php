<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Attachment = 'attachment';
    case Unsupported = 'unsupported';
}
