<?php

declare(strict_types=1);

namespace App\Domain\Operations\Data;

use App\Domain\Operations\Enums\DeploymentCheckStatus;

final readonly class DeploymentCheck
{
    public function __construct(
        public string $name,
        public DeploymentCheckStatus $status,
        public string $message,
    ) {}
}
