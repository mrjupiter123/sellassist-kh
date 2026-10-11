<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Data\DeploymentCheck;
use App\Domain\Operations\Enums\DeploymentCheckStatus as Status;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DeploymentReadinessService
{
    public function __construct(
        private readonly Migrator $migrator,
        private readonly ExternalOperationsHealth $externalHealth,
    ) {}

    /** @return list<DeploymentCheck> */
    public function check(): array
    {
        $production = config('app.env') === 'production';
        $checks = [];

        $checks[] = new DeploymentCheck(
            'PHP version',
            version_compare(PHP_VERSION, '8.3.0', '>=') ? Status::Pass : Status::Fail,
            version_compare(PHP_VERSION, '8.3.0', '>=') ? 'CLI PHP meets the 8.3 minimum.' : 'CLI PHP 8.3 or newer is required.',
        );

        $requiredExtensions = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo'];
        $missingExtensions = array_values(array_filter($requiredExtensions, fn (string $extension): bool => ! extension_loaded($extension)));
        $checks[] = new DeploymentCheck(
            'PHP extensions',
            $missingExtensions === [] ? Status::Pass : Status::Fail,
            $missingExtensions === [] ? 'Required CLI extensions are loaded.' : 'Missing CLI extensions: '.implode(', ', $missingExtensions).'.',
        );

        $checks[] = new DeploymentCheck(
            'Application key',
            filled(config('app.key')) ? Status::Pass : Status::Fail,
            filled(config('app.key')) ? 'Configured.' : 'Set APP_KEY before serving the site.',
        );
        $checks[] = new DeploymentCheck(
            'Debug mode',
            $production && config('app.debug') ? Status::Fail : Status::Pass,
            $production && config('app.debug') ? 'Turn APP_DEBUG off in production.' : 'Acceptable for this environment.',
        );
        $https = str_starts_with((string) config('app.url'), 'https://');
        $checks[] = new DeploymentCheck(
            'Application URL',
            $production && ! $https ? Status::Fail : Status::Pass,
            $production && ! $https ? 'Set APP_URL to the public HTTPS address.' : 'URL scheme is acceptable for this environment.',
        );

        $databaseReady = false;
        try {
            DB::connection()->select('select 1');
            $databaseReady = true;
            $checks[] = new DeploymentCheck('Database connection', Status::Pass, 'A read-only query succeeded.');
        } catch (\Throwable) {
            $checks[] = new DeploymentCheck('Database connection', Status::Fail, 'Cannot connect. Check DB settings and grants; no credentials are shown.');
        }

        if ($databaseReady) {
            $checks[] = $this->migrationCheck();
            $checks[] = $this->rolesCheck();
        } else {
            $checks[] = new DeploymentCheck('Migrations', Status::Warn, 'Not checked until the database connection works.');
            $checks[] = new DeploymentCheck('Roles', Status::Warn, 'Not checked until the database connection works.');
        }

        $queueDatabase = config('queue.default') === 'database';
        $checks[] = new DeploymentCheck(
            'Queue connection',
            $queueDatabase ? Status::Pass : ($production ? Status::Fail : Status::Warn),
            $queueDatabase ? 'Database queue is configured.' : 'Set QUEUE_CONNECTION=database for queued integrations on hosting.',
        );

        $writableDirectories = [
            'storage/framework/cache', 'storage/framework/sessions',
            'storage/framework/views', 'storage/logs', 'bootstrap/cache',
        ];
        $unwritable = array_values(array_filter($writableDirectories, fn (string $path): bool => ! is_writable(base_path($path))));
        $checks[] = new DeploymentCheck(
            'Writable directories',
            $unwritable === [] ? Status::Pass : Status::Fail,
            $unwritable === [] ? 'Required runtime directories are writable.' : 'Make these directories writable: '.implode(', ', $unwritable).'.',
        );

        $checks[] = new DeploymentCheck(
            'Public entrypoint',
            is_file(public_path('index.php')) && is_file(public_path('.htaccess')) ? Status::Pass : Status::Fail,
            is_file(public_path('index.php')) && is_file(public_path('.htaccess'))
                ? 'Laravel public/index.php and Apache rewrite rules exist.'
                : 'Check public/index.php and public/.htaccess.',
        );
        $assetsBuilt = is_file(public_path('build/manifest.json'));
        $checks[] = new DeploymentCheck(
            'Frontend assets',
            $assetsBuilt ? Status::Pass : ($production ? Status::Fail : Status::Warn),
            $assetsBuilt ? 'Vite manifest exists.' : 'Build assets or upload public/build from a local build.',
        );

        $checks[] = new DeploymentCheck(
            'Public storage link',
            is_link(public_path('storage')) ? Status::Pass : Status::Warn,
            is_link(public_path('storage')) ? 'Storage link exists.' : 'Run php artisan storage:link if public uploads are used.',
        );
        $mailReady = ! in_array(config('mail.default'), ['log', 'array', 'null'], true);
        $checks[] = new DeploymentCheck(
            'Mail transport',
            $mailReady ? Status::Pass : Status::Warn,
            $mailReady ? 'Non-local transport selected; use Alerts to verify delivery.' : 'Mail is not configured for external delivery.',
        );

        $token = config('operations.health_token');
        if ($token === null || $token === '') {
            $checks[] = new DeploymentCheck('External health check', Status::Pass, 'Optional external health check is disabled.');
        } elseif (! is_string($token) || strlen($token) < 32) {
            $checks[] = new DeploymentCheck('External health check', Status::Fail, 'OPERATIONS_HEALTH_TOKEN must contain at least 32 characters.');
        } elseif ($databaseReady) {
            try {
                $healthy = $this->externalHealth->isHealthy();
                $checks[] = new DeploymentCheck(
                    'External health check',
                    $healthy ? Status::Pass : Status::Warn,
                    $healthy
                        ? 'Scheduler, worker, and queue-monitor signals are fresh.'
                        : 'Health signals are not fresh yet; verify both cron entries.',
                );
            } catch (\Throwable) {
                $checks[] = new DeploymentCheck('External health check', Status::Warn, 'Health signals are unavailable; run migrations and both cron entries.');
            }
        } else {
            $checks[] = new DeploymentCheck('External health check', Status::Warn, 'Not checked until the database connection works.');
        }

        return $checks;
    }

    private function migrationCheck(): DeploymentCheck
    {
        try {
            if (! $this->migrator->repositoryExists()) {
                return new DeploymentCheck('Migrations', Status::Fail, 'Migration repository is missing; run php artisan migrate --force.');
            }

            $available = array_keys($this->migrator->getMigrationFiles(database_path('migrations')));
            $pending = array_diff($available, $this->migrator->getRepository()->getRan());

            return new DeploymentCheck(
                'Migrations',
                $pending === [] ? Status::Pass : Status::Fail,
                $pending === [] ? 'No pending migrations.' : count($pending).' migration(s) pending; run php artisan migrate --force.',
            );
        } catch (\Throwable) {
            return new DeploymentCheck('Migrations', Status::Fail, 'Unable to inspect migrations.');
        }
    }

    private function rolesCheck(): DeploymentCheck
    {
        try {
            if (! Schema::hasTable('roles')) {
                return new DeploymentCheck('Roles', Status::Fail, 'Permission tables are missing; run migrations.');
            }

            $count = DB::table('roles')->whereIn('name', ['admin', 'staff'])->distinct()->count('name');

            return new DeploymentCheck(
                'Roles',
                $count === 2 ? Status::Pass : Status::Fail,
                $count === 2 ? 'Admin and staff roles exist.' : 'Seed roles with RolePermissionSeeder.',
            );
        } catch (\Throwable) {
            return new DeploymentCheck('Roles', Status::Fail, 'Unable to inspect roles.');
        }
    }
}
