<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum AiAlertMailStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sent => 'Handed to mailer',
            self::Skipped => 'Skipped',
            self::Failed => 'Failed after retries',
        };
    }
}
