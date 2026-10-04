<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum OrderExtractionStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }

    public function isPending(): bool
    {
        return in_array($this, [self::Queued, self::Processing], true);
    }
}
