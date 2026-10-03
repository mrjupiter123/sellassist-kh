<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Customer\Models\Customer;
use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use App\Domain\Social\Actions\ConfigureTelegramBot;
use App\Domain\Social\Actions\CreateCustomerFromSocialContact;
use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Models\SocialChannel;
use App\Domain\Social\Models\SocialContact;
use App\Domain\Social\Models\SocialConversation;
use App\Domain\Social\Models\SocialMessage;
use App\Domain\Social\Models\SocialWebhookEvent;
use App\Jobs\ProcessTelegramWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramOrderIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_configurator_validates_bot_registers_signed_webhook_and_creates_channel(): void
    {
        config([
            'app.url' => 'https://staging.sellassist.test',
            'social.telegram.bot_token' => 'test-bot-token',
            'social.telegram.webhook_secret' => 'valid_webhook_secret_123',
        ]);
        Http::fake(function (Request $request) {
            return match (true) {
                str_ends_with($request->url(), '/getMe') => Http::response([
                    'ok' => true,
                    'result' => ['id' => 90001, 'first_name' => 'SellAssist', 'username' => 'sellassist_test_bot'],
                ]),
                str_ends_with($request->url(), '/setWebhook') => Http::response(['ok' => true, 'result' => true]),
                default => Http::response([
                    'ok' => true,
                    'result' => ['url' => 'https://staging.sellassist.test/api/social/telegram/webhook/example', 'pending_update_count' => 0],
                ]),
            };
        });

        $result = app(ConfigureTelegramBot::class)->execute();

        $channel = $result['channel'];
        $this->assertSame(SocialPlatform::Telegram, $channel->platform);
        $this->assertSame('90001', $channel->external_id);
        $this->assertSame('@sellassist_test_bot', $channel->name);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/setWebhook')
            && $request['secret_token'] === 'valid_webhook_secret_123'
            && $request['allowed_updates'] === ['message']
            && str_contains((string) $request['url'], '/api/social/telegram/webhook/'.$channel->uuid));
    }

    public function test_signed_webhook_is_queued_encrypted_and_deduplicated(): void
    {
        config(['social.telegram.webhook_secret' => 'valid_webhook_secret_123']);
        $channel = $this->telegramChannel();
        Queue::fake();
        $raw = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $route = route('api.social.telegram.webhook.receive', $channel);

        $this->call('POST', $route, [], [], [], [], $raw)->assertUnauthorized();
        $headers = ['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'valid_webhook_secret_123'];
        $this->call('POST', $route, [], [], [], $headers, $raw)
            ->assertOk()->assertJson(['accepted' => true, 'duplicate' => false]);
        $this->call('POST', $route, [], [], [], $headers, $raw)
            ->assertOk()->assertJson(['accepted' => true, 'duplicate' => true]);

        $this->assertDatabaseCount('social_webhook_events', 1);
        $event = SocialWebhookEvent::query()->sole();
        $this->assertSame($channel->id, $event->social_channel_id);
        $this->assertStringNotContainsString('Telegram order', (string) $event->getRawOriginal('payload'));
        Queue::assertPushed(ProcessTelegramWebhook::class, 1);
    }

    public function test_job_creates_contact_and_message_idempotently(): void
    {
        $channel = $this->telegramChannel();
        $event = SocialWebhookEvent::query()->create([
            'social_channel_id' => $channel->id,
            'platform' => SocialPlatform::Telegram,
            'event_id' => hash('sha256', 'telegram-event'),
            'status' => 'received',
            'payload' => $this->payload(),
            'received_at' => now(),
        ]);

        app()->call([new ProcessTelegramWebhook($event->id), 'handle']);
        app()->call([new ProcessTelegramWebhook($event->id), 'handle']);

        $this->assertDatabaseCount('social_contacts', 1);
        $this->assertDatabaseCount('social_conversations', 1);
        $this->assertDatabaseCount('social_messages', 1);
        $this->assertSame('Sokha (@sokha_shop)', SocialContact::query()->sole()->display_name);
        $message = SocialMessage::query()->sole();
        $this->assertSame('Telegram order: 2 shirts', $message->body);
        $this->assertStringNotContainsString('Telegram order', (string) $message->getRawOriginal('body'));
        $this->assertSame('processed', $event->refresh()->status->value);
    }

    public function test_linked_telegram_conversation_creates_server_priced_draft_without_stock_change(): void
    {
        $user = $this->userWithPermissions(['social.view', 'social.manage', 'orders.create']);
        $customer = Customer::factory()->create();
        $channel = $this->telegramChannel();
        $contact = SocialContact::query()->create([
            'social_channel_id' => $channel->id,
            'customer_id' => $customer->id,
            'external_id' => '70001',
            'display_name' => 'Sokha',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $conversation = SocialConversation::query()->create([
            'social_channel_id' => $channel->id,
            'social_contact_id' => $contact->id,
            'status' => 'open',
            'last_message_at' => now(),
        ]);
        $product = Product::factory()->create(['base_price' => '8.50']);
        app(AdjustStock::class)->execute($product, null, 5, StockMovementType::StockIn, $user);

        $this->actingAs($user)->post(route('social.inbox.draft-order.store', $conversation), [
            'currency' => 'USD',
            'discount' => '0',
            'delivery_fee' => '1.00',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'discount' => '0']],
        ])->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame('telegram', $order->source->value);
        $this->assertSame('draft', $order->status->value);
        $this->assertSame('18.00', $order->total);
        $this->assertSame('8.50', $order->items()->sole()->unit_price);
        $this->assertSame(5, $product->refresh()->stock_quantity);
    }

    public function test_customer_created_from_telegram_uses_telegram_source_without_facebook_identity(): void
    {
        $channel = $this->telegramChannel();
        $contact = SocialContact::query()->create([
            'social_channel_id' => $channel->id,
            'external_id' => '70001',
            'display_name' => 'Sokha (@sokha_shop)',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $customer = app(CreateCustomerFromSocialContact::class)->execute($contact, [
            'name' => 'Sokha',
            'phone' => '012345678',
        ]);

        $this->assertSame('telegram', $customer->source->value);
        $this->assertNull($customer->facebook_name);
        $this->assertSame($customer->id, $contact->refresh()->customer_id);
    }

    private function telegramChannel(): SocialChannel
    {
        return SocialChannel::query()->create([
            'platform' => SocialPlatform::Telegram,
            'name' => '@sellassist_test_bot',
            'external_id' => '90001',
            'active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'update_id' => 501,
            'message' => [
                'message_id' => 11,
                'date' => 1790985600,
                'chat' => ['id' => 70001, 'type' => 'private'],
                'from' => ['id' => 70001, 'first_name' => 'Sokha', 'username' => 'sokha_shop'],
                'text' => 'Telegram order: 2 shirts',
            ],
        ];
    }
}
