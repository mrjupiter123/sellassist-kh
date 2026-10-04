<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Social\Enums\ConversationStatus;
use App\Domain\Social\Enums\MessageDirection;
use App\Domain\Social\Enums\MessageType;
use App\Domain\Social\Enums\SocialMessageDeliveryStatus;
use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Exceptions\SocialMessageDeliveryException;
use App\Domain\Social\Models\SocialChannel;
use App\Domain\Social\Models\SocialContact;
use App\Domain\Social\Models\SocialConversation;
use App\Domain\Social\Models\SocialMessage;
use App\Jobs\SendSocialMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SocialReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_seller_can_queue_encrypted_reply(): void
    {
        Queue::fake();
        $user = $this->userWithPermissions(['social.view', 'social.reply']);
        $conversation = $this->conversation(SocialPlatform::Telegram);

        $this->actingAs($user)
            ->post(route('social.inbox.replies.store', $conversation), ['body' => '  Your order is ready.  '])
            ->assertRedirect()
            ->assertSessionHas('success');

        $message = SocialMessage::query()->sole();
        $this->assertSame(MessageDirection::Outbound, $message->direction);
        $this->assertSame(SocialMessageDeliveryStatus::Pending, $message->delivery_status);
        $this->assertSame('Your order is ready.', $message->body);
        $this->assertSame($user->id, $message->created_by);
        $this->assertStringNotContainsString('Your order is ready.', (string) $message->getRawOriginal('body'));
        Queue::assertPushed(SendSocialMessage::class, fn (SendSocialMessage $job): bool => $job->messageId === $message->id);
    }

    public function test_user_without_reply_permission_cannot_queue_reply(): void
    {
        Queue::fake();
        $user = $this->userWithPermissions(['social.view']);

        $this->actingAs($user)
            ->post(route('social.inbox.replies.store', $this->conversation()), ['body' => 'Hello'])
            ->assertForbidden();

        $this->assertDatabaseCount('social_messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_archived_conversation_rejects_reply(): void
    {
        Queue::fake();
        $user = $this->userWithPermissions(['social.reply']);
        $conversation = $this->conversation(status: ConversationStatus::Archived);

        $this->actingAs($user)
            ->from(route('social.inbox.show', $conversation))
            ->post(route('social.inbox.replies.store', $conversation), ['body' => 'Hello'])
            ->assertRedirect(route('social.inbox.show', $conversation))
            ->assertSessionHas('error', 'Archived conversations cannot receive replies.');

        $this->assertDatabaseCount('social_messages', 0);
    }

    public function test_inactive_channel_and_blank_message_are_rejected(): void
    {
        Queue::fake();
        $user = $this->userWithPermissions(['social.reply']);
        $conversation = $this->conversation();

        $this->actingAs($user)
            ->post(route('social.inbox.replies.store', $conversation), ['body' => '   '])
            ->assertSessionHasErrors('body');

        $conversation->channel()->update(['active' => false]);
        $this->actingAs($user)
            ->post(route('social.inbox.replies.store', $conversation), ['body' => 'Hello'])
            ->assertSessionHas('error', 'This social channel is inactive and cannot send replies.');

        $this->assertDatabaseCount('social_messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_telegram_reply_job_sends_and_records_provider_result(): void
    {
        config(['social.telegram.bot_token' => 'test-token']);
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 77, 'date' => 1791072000, 'text' => 'Hello Telegram'],
            ]),
        ]);
        $message = $this->outboundMessage($this->conversation(SocialPlatform::Telegram), 'Hello Telegram');

        app()->call([new SendSocialMessage($message->id), 'handle']);

        $message->refresh();
        $this->assertSame(SocialMessageDeliveryStatus::Sent, $message->delivery_status);
        $this->assertSame('telegram:customer-100:77', $message->external_id);
        $this->assertNull($message->delivery_error);
        Http::assertSent(fn (Request $request): bool => $request['chat_id'] === 'customer-100'
            && $request['text'] === 'Hello Telegram');
    }

    public function test_meta_reply_job_sends_with_bearer_token(): void
    {
        config([
            'social.meta.page_access_token' => 'page-token',
            'social.meta.graph_version' => 'v26.0',
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'recipient_id' => 'customer-100',
                'message_id' => 'mid.100',
            ]),
        ]);
        $message = $this->outboundMessage($this->conversation(), 'Hello Messenger');

        app()->call([new SendSocialMessage($message->id), 'handle']);

        $message->refresh();
        $this->assertSame(SocialMessageDeliveryStatus::Sent, $message->delivery_status);
        $this->assertSame('mid.100', $message->external_id);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer page-token')
            && $request['recipient']['id'] === 'customer-100'
            && $request['message']['text'] === 'Hello Messenger');
    }

    public function test_provider_failure_is_recorded_without_storing_secret_details(): void
    {
        config(['social.meta.page_access_token' => 'page-token']);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['code' => 10, 'message' => 'Denied']], 400),
        ]);
        $message = $this->outboundMessage($this->conversation(), 'Hello');

        try {
            app()->call([new SendSocialMessage($message->id), 'handle']);
            $this->fail('The provider failure should throw an exception for queue retry.');
        } catch (SocialMessageDeliveryException $exception) {
            $this->assertSame('Meta rejected the message with error code 10.', $exception->getMessage());
        }

        $message->refresh();
        $this->assertSame(SocialMessageDeliveryStatus::Failed, $message->delivery_status);
        $this->assertSame('Meta rejected the message with error code 10.', $message->delivery_error);
        $this->assertStringNotContainsString('page-token', $message->delivery_error);
    }

    private function conversation(
        SocialPlatform $platform = SocialPlatform::FacebookMessenger,
        ConversationStatus $status = ConversationStatus::Open,
    ): SocialConversation {
        $channel = SocialChannel::query()->create([
            'platform' => $platform,
            'name' => $platform->label(),
            'external_id' => $platform === SocialPlatform::Telegram ? 'bot-100' : 'page-100',
            'active' => true,
        ]);
        $contact = SocialContact::query()->create([
            'social_channel_id' => $channel->id,
            'external_id' => 'customer-100',
            'display_name' => 'Test Customer',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        return SocialConversation::query()->create([
            'social_channel_id' => $channel->id,
            'social_contact_id' => $contact->id,
            'status' => $status,
            'last_message_at' => now(),
        ]);
    }

    private function outboundMessage(SocialConversation $conversation, string $body): SocialMessage
    {
        return SocialMessage::query()->create([
            'social_conversation_id' => $conversation->id,
            'external_id' => 'local:test-'.$conversation->id,
            'direction' => MessageDirection::Outbound,
            'delivery_status' => SocialMessageDeliveryStatus::Pending,
            'type' => MessageType::Text,
            'body' => $body,
            'sent_at' => now(),
        ]);
    }
}
