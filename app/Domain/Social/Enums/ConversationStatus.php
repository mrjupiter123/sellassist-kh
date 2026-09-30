<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum ConversationStatus: string
{
    case Open = 'open';
    case Converted = 'converted';
    case Archived = 'archived';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
