<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum AiEvaluationRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
