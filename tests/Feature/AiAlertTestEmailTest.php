<?php

declare(strict_types=1);

namespace Tests\Feature;

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
        Queue::assertPushed(SendAiAlertTestEmail::class, function (SendAiAlertTestEmail $job) use ($admin): bool {
            return $job->userId === $admin->id && $job->queue === 'integrations';
        });

        Mail::fake();
        Queue::pushed(SendAiAlertTestEmail::class)->first()->handle();
        Mail::assertSent(AiAlertTestMail::class, function (AiAlertTestMail $mail) use ($admin): bool {
            return $mail->hasTo($admin->email)
                && str_contains($mail->render(), 'No AI release alert was triggered.');
        });
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
}
