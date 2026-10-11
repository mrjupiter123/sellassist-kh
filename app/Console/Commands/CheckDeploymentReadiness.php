<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Operations\Enums\DeploymentCheckStatus;
use App\Domain\Operations\Services\DeploymentReadinessService;
use Illuminate\Console\Command;

class CheckDeploymentReadiness extends Command
{
    protected $signature = 'operations:deployment-check {--strict : Return failure if any warnings remain}';

    protected $description = 'Read-only deployment checks for cPanel and other Laravel hosting';

    public function handle(DeploymentReadinessService $readiness): int
    {
        $checks = $readiness->check();
        $this->table(
            ['Check', 'Status', 'Result'],
            array_map(fn ($check): array => [$check->name, $check->status->value, $check->message], $checks),
        );
        $this->warn('Manual checks still required: cPanel document root, web PHP version, HTTPS certificate, cron entries, backups, and a real mail/integration test.');

        $failed = count(array_filter($checks, fn ($check): bool => $check->status === DeploymentCheckStatus::Fail));
        $warnings = count(array_filter($checks, fn ($check): bool => $check->status === DeploymentCheckStatus::Warn));
        $this->line("Checks complete: {$failed} failed, {$warnings} warning(s).");

        return $failed > 0 || ($this->option('strict') && $warnings > 0)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
