<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum AiProfileReleaseType: string
{
    case Activation = 'activation';
    case Rollback = 'rollback';

    public function label(): string
    {
        return match ($this) {
            self::Activation => 'Activation',
            self::Rollback => 'Rollback',
        };
    }
}
