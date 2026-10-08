<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum AiEvaluationDatasetStatus: string
{
    case Frozen = 'frozen';

    public function label(): string
    {
        return match ($this) {
            self::Frozen => 'Frozen',
        };
    }
}
