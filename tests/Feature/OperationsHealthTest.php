<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationsHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_view_health_and_retry_known_failed_job(): void
    {
        $user = $this->userWithPermissions(['operations.view', 'operations.retry']);
        DB::table('failed_jobs')->insert([
            'uuid' => 'failed-job-uuid',
            'connection' => 'database',
            'queue' => 'integrations',
            'payload' => '{}',
            'exception' => 'Test integration failure',
            'failed_at' => now(),
        ]);

        $this->actingAs($user)->get(route('operations.index'))
            ->assertOk()
            ->assertSee('Test integration failure');

        Artisan::shouldReceive('call')->once()->with('queue:retry', ['id' => ['failed-job-uuid']])->andReturn(0);
        $this->actingAs($user)->post(route('operations.failed-jobs.retry', 'failed-job-uuid'))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_staff_without_operations_permission_cannot_view_or_retry_jobs(): void
    {
        $user = $this->userWithPermissions(['orders.view']);

        $this->actingAs($user)->get(route('operations.index'))->assertForbidden();
        $this->actingAs($user)->post(route('operations.failed-jobs.retry', 'missing'))->assertForbidden();
    }
}
