<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Operations\Enums\DeploymentCheckStatus;
use App\Domain\Operations\Services\DeploymentReadinessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_check_reports_database_migrations_and_roles_without_secrets(): void
    {
        $this->seed(RolePermissionSeeder::class);
        config(['mail.mailers.smtp.password' => 'private-test-password-must-not-appear']);

        $checks = collect(app(DeploymentReadinessService::class)->check())->keyBy('name');

        $this->assertSame(DeploymentCheckStatus::Pass, $checks->get('Database connection')->status);
        $this->assertSame(DeploymentCheckStatus::Pass, $checks->get('Migrations')->status);
        $this->assertSame(DeploymentCheckStatus::Pass, $checks->get('Roles')->status);
        $this->assertSame(DeploymentCheckStatus::Pass, $checks->get('Public entrypoint')->status);
        $this->assertStringNotContainsString(
            'private-test-password-must-not-appear',
            $checks->pluck('message')->implode(' '),
        );
    }

    public function test_strict_mode_fails_on_warnings_while_normal_mode_allows_them(): void
    {
        $this->seed(RolePermissionSeeder::class);
        config(['mail.default' => 'log', 'queue.default' => 'database']);

        $normal = Artisan::call('operations:deployment-check');
        $strict = Artisan::call('operations:deployment-check', ['--strict' => true]);

        $this->assertSame(0, $normal);
        $this->assertSame(1, $strict);
        $this->assertStringContainsString('Mail is not configured for external delivery.', Artisan::output());
    }

    public function test_unsafe_production_configuration_fails_without_printing_keys(): void
    {
        $this->seed(RolePermissionSeeder::class);
        config([
            'app.env' => 'production',
            'app.debug' => true,
            'app.url' => 'http://example.test',
            'app.key' => 'private-app-key-must-not-appear',
            'queue.default' => 'sync',
        ]);

        $checks = collect(app(DeploymentReadinessService::class)->check())->keyBy('name');

        $this->assertSame(DeploymentCheckStatus::Fail, $checks->get('Debug mode')->status);
        $this->assertSame(DeploymentCheckStatus::Fail, $checks->get('Application URL')->status);
        $this->assertSame(DeploymentCheckStatus::Fail, $checks->get('Queue connection')->status);
        $this->assertStringNotContainsString('private-app-key-must-not-appear', $checks->pluck('message')->implode(' '));
        $this->assertSame(1, Artisan::call('operations:deployment-check'));
    }

    public function test_database_failure_is_reported_without_connection_details(): void
    {
        $original = config('database.default');
        config(['database.default' => 'missing-test-connection']);

        try {
            $checks = collect(app(DeploymentReadinessService::class)->check())->keyBy('name');

            $this->assertSame(DeploymentCheckStatus::Fail, $checks->get('Database connection')->status);
            $this->assertSame(DeploymentCheckStatus::Warn, $checks->get('Migrations')->status);
            $this->assertSame(DeploymentCheckStatus::Warn, $checks->get('Roles')->status);
            $this->assertStringNotContainsString('missing-test-connection', $checks->pluck('message')->implode(' '));
        } finally {
            config(['database.default' => $original]);
        }
    }
}
