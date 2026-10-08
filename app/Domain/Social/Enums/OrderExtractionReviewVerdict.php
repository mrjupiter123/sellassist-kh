<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum OrderExtractionReviewVerdict: string
{
    case Accepted = 'accepted';
    case Corrected = 'corrected';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Useful as suggested',
            self::Corrected => 'Useful after corrections',
            self::Rejected => 'Not useful',
        };
    }
}
