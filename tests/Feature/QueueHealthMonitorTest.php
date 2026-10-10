<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Operations\Enums\QueueHealthStatus;
use App\Domain\Operations\Models\QueueStallAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueHealthMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database', 'operations.queue_stale_minutes' => 15]);
    }

    public function test_stale_ready_jobs_alert_active_operations_administrators_once_until_recovery(): void
    {
        $admin = $this->userWithPermissions(['operations.view']);
        $staff = $this->userWithPermissions(['orders.view']);
        $this->insertJob('integrations', now()->subMinutes(20)->timestamp);

        $this->artisan('operations:monitor-queues')->assertSuccessful();
        $alert = QueueStallAlert::query()->where('queue', 'integrations')->sole();
        $this->assertSame(QueueHealthStatus::Stalled, $alert->status);
        $this->assertSame(1, $alert->stale_jobs);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(0, $staff->notifications()->count());
        $this->assertSame('database_queue_stalled', $admin->notifications()->firstOrFail()->data['kind']);
        $this->actingAs($admin)->get(route('operations.index'))
            ->assertOk()
            ->assertSeeText('Integrations queue: stalled')
            ->assertSeeText('1 ready job(s) have waited at least 15 minutes');

        $this->artisan('operations:monitor-queues')->assertSuccessful();
        $this->assertSame(1, $admin->notifications()->count());

        DB::table('jobs')->delete();
        $this->artisan('operations:monitor-queues')->assertSuccessful();
        $this->assertSame(QueueHealthStatus::Healthy, $alert->refresh()->status);
        $this->assertNotNull($alert->recovered_at);
        $this->assertNull($alert->last_notified_at);

        $this->insertJob('integrations', now()->subMinutes(20)->timestamp);
        $this->artisan('operations:monitor-queues')->assertSuccessful();
        $this->assertSame(2, $admin->notifications()->count());
    }

    public function test_delayed_and_recently_reserved_jobs_are_not_called_stalled(): void
    {
        $admin = $this->userWithPermissions(['operations.view']);
        $this->insertJob('integrations', now()->addHour()->timestamp);
        $this->insertJob('default', now()->subMinutes(20)->timestamp, now()->subMinute()->timestamp);

        $this->artisan('operations:monitor-queues')->assertSuccessful();

        $this->assertSame(0, $admin->notifications()->count());
        $this->assertSame(2, QueueStallAlert::query()->where('status', QueueHealthStatus::Healthy)->count());
    }

    public function test_monitor_does_not_claim_to_check_non_database_queue_connections(): void
    {
        config(['queue.default' => 'sync']);

        $this->artisan('operations:monitor-queues')->expectsOutput('monitor: unsupported')->assertSuccessful();
        $this->assertDatabaseCount('queue_stall_alerts', 0);
    }

    public function test_operations_page_warns_when_scheduler_has_not_run_recently(): void
    {
        $admin = $this->userWithPermissions(['operations.view']);
        $this->actingAs($admin)->get(route('operations.index'))
            ->assertOk()
            ->assertSeeText('Queue monitor overdue. Check the Laravel scheduler cron.');

        QueueStallAlert::query()->create([
            'queue' => 'integrations',
            'status' => QueueHealthStatus::Healthy,
            'last_checked_at' => now()->subMinutes(20),
        ]);
        $this->actingAs($admin)->get(route('operations.index'))
            ->assertSeeText('Integrations queue: healthy')
            ->assertSeeText('Queue monitor overdue. Check the Laravel scheduler cron.');
    }

    private function insertJob(string $queue, int $availableAt, ?int $reservedAt = null): void
    {
        DB::table('jobs')->insert([
            'queue' => $queue,
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => $reservedAt,
            'available_at' => $availableAt,
            'created_at' => now()->subMinutes(20)->timestamp,
        ]);
    }
}
