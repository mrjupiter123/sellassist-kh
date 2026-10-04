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

    public function allowsReplies(): bool
    {
        return $this !== self::Archived;
    }

    public function isArchived(): bool
    {
        return $this === self::Archived;
    }

    public function toggleTarget(): self
    {
        return $this->isArchived() ? self::Open : self::Archived;
    }
}
