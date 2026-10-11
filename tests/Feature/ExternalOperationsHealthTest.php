<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Operations\Enums\OperationsHeartbeatName;
use App\Domain\Operations\Enums\QueueHealthStatus;
use App\Domain\Operations\Models\OperationsHeartbeat;
use App\Domain\Operations\Models\QueueStallAlert;
use App\Jobs\RecordIntegrationsWorkerHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExternalOperationsHealthTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-only-health-token-with-at-least-32-chars';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'queue.default' => 'database',
            'operations.health_token' => self::TOKEN,
        ]);
    }

    public function test_health_endpoint_is_disabled_without_token_and_never_accepts_a_query_token(): void
    {
        config(['operations.health_token' => null]);
        $this->get(route('api.health.operations'))->assertNotFound();

        config(['operations.health_token' => self::TOKEN]);
        $this->get(route('api.health.operations', ['token' => self::TOKEN]))->assertNotFound();
        $this->withToken('wrong-token')->get(route('api.health.operations'))->assertNotFound();
        $this->assertDatabaseCount('operations_heartbeats', 0);
    }

    public function test_scheduler_and_worker_probes_are_both_required_for_healthy_response(): void
    {
        Queue::fake();
        $this->withToken(self::TOKEN)->get(route('api.health.operations'))->assertStatus(503)->assertDontSee('scheduler');

        $this->artisan('operations:monitor-queues')->assertSuccessful();
        $this->artisan('operations:heartbeat')->assertSuccessful();
        Queue::assertPushed(RecordIntegrationsWorkerHeartbeat::class, function (RecordIntegrationsWorkerHeartbeat $job): bool {
            return $job->queue === 'integrations';
        });
        $this->assertDatabaseHas('operations_heartbeats', ['name' => OperationsHeartbeatName::Scheduler->value]);
        $this->withToken(self::TOKEN)->get(route('api.health.operations'))->assertStatus(503);

        app()->call([Queue::pushed(RecordIntegrationsWorkerHeartbeat::class)->first(), 'handle']);
        $this->withToken(self::TOKEN)->get(route('api.health.operations'))
            ->assertNoContent()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_stale_heartbeats_and_stalled_queue_return_unhealthy_without_disclosing_details(): void
    {
        Queue::fake();
        $this->artisan('operations:monitor-queues')->assertSuccessful();
        $this->artisan('operations:heartbeat')->assertSuccessful();
        app()->call([Queue::pushed(RecordIntegrationsWorkerHeartbeat::class)->first(), 'handle']);

        OperationsHeartbeat::query()->where('name', OperationsHeartbeatName::IntegrationsWorker->value)
            ->update(['last_seen_at' => now()->subMinutes(16)]);
        $this->withToken(self::TOKEN)->get(route('api.health.operations'))->assertStatus(503)->assertDontSee('integrations');

        OperationsHeartbeat::query()->where('name', OperationsHeartbeatName::IntegrationsWorker->value)
            ->update(['last_seen_at' => now()]);
        OperationsHeartbeat::query()->where('name', OperationsHeartbeatName::Scheduler->value)
            ->update(['last_seen_at' => now()->subMinutes(16)]);
        $this->withToken(self::TOKEN)->get(route('api.health.operations'))->assertStatus(503);

        OperationsHeartbeat::query()->where('name', OperationsHeartbeatName::Scheduler->value)
            ->update(['last_seen_at' => now()]);
        QueueStallAlert::query()->where('queue', 'integrations')->update(['status' => QueueHealthStatus::Stalled]);
        $this->withToken(self::TOKEN)->get(route('api.health.operations'))->assertStatus(503);

        QueueStallAlert::query()->where('queue', 'integrations')->update([
            'status' => QueueHealthStatus::Healthy,
            'last_checked_at' => now()->subMinutes(16),
        ]);
        $this->withToken(self::TOKEN)->get(route('api.health.operations'))->assertStatus(503);
    }

    public function test_probe_command_does_not_enqueue_when_monitoring_is_disabled(): void
    {
        Queue::fake();
        config(['operations.health_token' => null]);

        $this->artisan('operations:heartbeat')->assertSuccessful();

        Queue::assertNotPushed(RecordIntegrationsWorkerHeartbeat::class);
        $this->assertDatabaseCount('operations_heartbeats', 0);
    }
}
