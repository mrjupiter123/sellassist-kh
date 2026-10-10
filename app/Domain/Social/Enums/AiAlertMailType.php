<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum AiAlertMailType: string
{
    case Test = 'test';
    case Degradation = 'degradation';

    public function label(): string
    {
        return match ($this) {
            self::Test => 'Test email',
            self::Degradation => 'Degradation alert',
        };
    }
}
