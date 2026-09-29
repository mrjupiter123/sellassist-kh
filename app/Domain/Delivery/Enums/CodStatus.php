<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Enums;

enum CodStatus: string
{
    case NotApplicable = 'not_applicable';
    case PendingCollection = 'pending_collection';
    case Collected = 'collected';
    case PartiallyRemitted = 'partially_remitted';
    case Remitted = 'remitted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
