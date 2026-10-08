<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Social\Enums\ConversationStatus;
use App\Domain\Social\Enums\OrderExtractionReviewVerdict;
use App\Domain\Social\Enums\OrderExtractionStatus;
use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Models\SocialChannel;
use App\Domain\Social\Models\SocialContact;
use App\Domain\Social\Models\SocialConversation;
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

    public function test_operations_dashboard_reports_ai_quality_without_displaying_encrypted_notes(): void
    {
        $user = $this->userWithPermissions(['operations.view']);
        $channel = SocialChannel::query()->create([
            'platform' => SocialPlatform::FacebookMessenger,
            'name' => 'Quality Test Page',
            'external_id' => 'quality-page',
            'active' => true,
        ]);
        $contact = SocialContact::query()->create([
            'social_channel_id' => $channel->id,
            'external_id' => 'quality-contact',
            'display_name' => 'Private Customer Name',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $conversation = SocialConversation::query()->create([
            'social_channel_id' => $channel->id,
            'social_contact_id' => $contact->id,
            'status' => ConversationStatus::Open,
            'last_message_at' => now(),
        ]);
        $ready = $conversation->orderExtractions()->create([
            'status' => OrderExtractionStatus::Ready,
            'provider' => 'openai',
            'model' => 'quality-model',
            'input_hash' => hash('sha256', 'ready'),
            'message_count' => 1,
            'source_message_ids' => [1],
            'overall_confidence' => 0.55,
            'input_tokens' => 100,
            'output_tokens' => 40,
            'total_tokens' => 140,
            'processed_at' => now(),
            'requested_by' => $user->id,
        ]);
        $ready->review()->create([
            'verdict' => OrderExtractionReviewVerdict::Corrected,
            'customer_fields_correct' => true,
            'item_matches_correct' => false,
            'quantities_correct' => false,
            'notes' => 'Sensitive correction details must remain hidden.',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);
        $conversation->orderExtractions()->create([
            'status' => OrderExtractionStatus::Failed,
            'provider' => 'openai',
            'model' => 'quality-model',
            'input_hash' => hash('sha256', 'failed'),
            'message_count' => 1,
            'source_message_ids' => [2],
            'error' => 'Safe provider error',
            'processed_at' => now(),
            'requested_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('operations.index', ['period' => 30]))
            ->assertOk()
            ->assertSeeText('AI extraction quality')
            ->assertSeeText('quality-model')
            ->assertSeeText('50.0%')
            ->assertSeeText('140')
            ->assertSeeText('Useful after corrections')
            ->assertDontSeeText('Sensitive correction details')
            ->assertDontSeeText('Private Customer Name');
    }

    public function test_operations_quality_period_rejects_unsupported_values(): void
    {
        $user = $this->userWithPermissions(['operations.view']);

        $this->actingAs($user)
            ->get(route('operations.index', ['period' => 365]))
            ->assertSessionHasErrors('period');
    }
}
