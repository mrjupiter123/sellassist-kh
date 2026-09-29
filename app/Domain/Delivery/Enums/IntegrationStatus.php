<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Enums;

enum IntegrationStatus: string
{
    case Manual = 'manual';
    case NotSubmitted = 'not_submitted';
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
