<?php

declare(strict_types=1);

namespace App\Domain\Social\Enums;

enum AiEvaluationResultStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Error = 'error';
}
