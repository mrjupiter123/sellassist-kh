<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum DeploymentCheckStatus: string
{
    case Pass = 'PASS';
    case Warn = 'WARN';
    case Fail = 'FAIL';
}
