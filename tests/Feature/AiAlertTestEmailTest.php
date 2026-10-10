<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Social\Enums\AiAlertMailStatus;
use App\Domain\Social\Models\AiAlertMailAttempt;
use App\Jobs\SendAiAlertTestEmail;
use App\Mail\AiAlertTestMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiAlertTestEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_administrator_can_queue_a_test_email_without_enabling_alert_email(): void
    {
        Queue::fake();
        $admin = $this->userWithPermissions(['social.ai.manage']);

        $this->actingAs($admin)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Send test email to my account');

        $this->actingAs($admin)->post(route('notifications.ai-alert-test-email'))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('social_ai_alert_preferences', 0);
        $attempt = AiAlertMailAttempt::query()->sole();
        $this->assertSame(AiAlertMailStatus::Queued, $attempt->status);
        Queue::assertPushed(SendAiAlertTestEmail::class, function (SendAiAlertTestEmail $job) use ($admin): bool {
            return $job->userId === $admin->id && $job->attemptId !== null && $job->queue === 'integrations';
        });

        Mail::fake();
        Queue::pushed(SendAiAlertTestEmail::class)->first()->handle();
        Mail::assertSent(AiAlertTestMail::class, function (AiAlertTestMail $mail) use ($admin): bool {
            return $mail->hasTo($admin->email)
                && str_contains($mail->render(), 'No AI release alert was triggered.');
        });
        $this->assertSame(AiAlertMailStatus::Sent, $attempt->refresh()->status);
        $this->assertNotNull($attempt->processed_at);
        Queue::pushed(SendAiAlertTestEmail::class)->first()->handle();
        Mail::assertSent(AiAlertTestMail::class, 1);
        $this->actingAs($admin)->get(route('notifications.index'))
            ->assertSeeText('Test email')
            ->assertSeeText('Handed to mailer');
    }

    public function test_staff_cannot_request_a_test_email(): void
    {
        Queue::fake();
        $staff = $this->userWithPermissions(['social.view']);

        $this->actingAs($staff)->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('Send test email to my account');
        $this->actingAs($staff)->post(route('notifications.ai-alert-test-email'))->assertForbidden();
        Queue::assertNotPushed(SendAiAlertTestEmail::class);
    }

    public function test_test_email_requests_are_rate_limited(): void
    {
        Queue::fake();
        $admin = $this->userWithPermissions(['social.ai.manage']);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($admin)->post(route('notifications.ai-alert-test-email'))->assertRedirect();
        }

        $this->actingAs($admin)->post(route('notifications.ai-alert-test-email'))->assertStatus(429);
        Queue::assertPushed(SendAiAlertTestEmail::class, 3);
        $this->assertDatabaseCount('ai_alert_mail_attempts', 3);
    }

    public function test_queued_test_email_is_skipped_if_administrator_is_deactivated_or_loses_permission(): void
    {
        Mail::fake();
        $admin = $this->userWithPermissions(['social.ai.manage']);
        $job = new SendAiAlertTestEmail($admin->id);

        $admin->update(['active' => false]);
        $job->handle();
        Mail::assertNothingSent();

        $admin->update(['active' => true]);
        $admin->revokePermissionTo('social.ai.manage');
        $job->handle();
        Mail::assertNothingSent();
    }

    public function test_log_mailer_warning_is_visible_to_ai_administrator(): void
    {
        config(['mail.default' => 'log']);
        $admin = $this->userWithPermissions(['social.ai.manage']);

        $this->actingAs($admin)->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeText('The mailer is set to log.');
    }

    public function test_administrator_sees_only_their_own_mail_attempt_history(): void
    {
        Queue::fake();
        $first = $this->userWithPermissions(['social.ai.manage']);
        $second = $this->userWithPermissions(['social.ai.manage']);

        $this->actingAs($first)->post(route('notifications.ai-alert-test-email'))->assertRedirect();
        AiAlertMailAttempt::query()->sole()->update(['status' => AiAlertMailStatus::Failed]);
        $this->actingAs($second)->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeText('No email attempts yet.')
            ->assertDontSeeText('Failed after retries');
    }

    public function test_test_email_attempt_is_skipped_after_permission_is_revoked(): void
    {
        Queue::fake();
        Mail::fake();
        $admin = $this->userWithPermissions(['social.ai.manage']);
        $this->actingAs($admin)->post(route('notifications.ai-alert-test-email'))->assertRedirect();
        $attempt = AiAlertMailAttempt::query()->sole();

        $admin->revokePermissionTo('social.ai.manage');
        Queue::pushed(SendAiAlertTestEmail::class)->first()->handle();

        Mail::assertNothingSent();
        $this->assertSame(AiAlertMailStatus::Skipped, $attempt->refresh()->status);
    }

    public function test_mail_attempt_is_marked_failed_after_queue_retries_are_exhausted(): void
    {
        Queue::fake();
        $admin = $this->userWithPermissions(['social.ai.manage']);
        $this->actingAs($admin)->post(route('notifications.ai-alert-test-email'))->assertRedirect();
        $job = Queue::pushed(SendAiAlertTestEmail::class)->first();

        $job->failed(new \RuntimeException('Sensitive SMTP detail must not be saved.'));

        $this->assertDatabaseHas('ai_alert_mail_attempts', [
            'id' => $job->attemptId,
            'status' => AiAlertMailStatus::Failed->value,
        ]);
        $this->actingAs($admin)->get(route('notifications.index'))
            ->assertSeeText('Failed after retries')
            ->assertDontSeeText('Sensitive SMTP detail must not be saved.');

        Mail::fake();
        $job->handle();
        Mail::assertSent(AiAlertTestMail::class, 1);
        $this->assertSame(AiAlertMailStatus::Sent, AiAlertMailAttempt::query()->sole()->status);
    }
}
