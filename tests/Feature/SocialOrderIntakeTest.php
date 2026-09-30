<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Customer\Models\Customer;
use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Order\Models\Order;
use App\Domain\Product\Models\Product;
use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Models\SocialChannel;
use App\Domain\Social\Models\SocialContact;
use App\Domain\Social\Models\SocialConversation;
use App\Domain\Social\Models\SocialMessage;
use App\Domain\Social\Models\SocialWebhookEvent;
use App\Jobs\ProcessMetaWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SocialOrderIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_verification_requires_matching_token(): void
    {
        config(['social.meta.verify_token' => 'verify-me']);

        $this->get('/api/social/meta/webhook?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=123')->assertForbidden();
        $this->get('/api/social/meta/webhook?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=123')
            ->assertOk()
            ->assertSeeText('123');
    }

    public function test_signed_webhook_is_persisted_queued_and_deduplicated(): void
    {
        config(['social.meta.app_secret' => 'meta-secret']);
        Queue::fake();
        $raw = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $headers = ['HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, 'meta-secret')];

        $this->call('POST', route('api.social.meta.webhook.receive'), [], [], [], [], $raw)->assertUnauthorized();
        $this->call('POST', route('api.social.meta.webhook.receive'), [], [], [], $headers, $raw)
            ->assertOk()->assertJson(['accepted' => true, 'duplicate' => false]);
        $this->call('POST', route('api.social.meta.webhook.receive'), [], [], [], $headers, $raw)
            ->assertOk()->assertJson(['accepted' => true, 'duplicate' => true]);

        $this->assertDatabaseCount('social_webhook_events', 1);
        Queue::assertPushed(ProcessMetaWebhook::class, 1);
        $encrypted = (string) SocialWebhookEvent::query()->sole()->getRawOriginal('payload');
        $this->assertStringNotContainsString('mid-100', $encrypted);
    }

    public function test_webhook_job_creates_inbox_message_and_only_suggests_customer_match(): void
    {
        SocialChannel::query()->create([
            'platform' => SocialPlatform::FacebookMessenger,
            'name' => 'Main Page',
            'external_id' => 'page-100',
            'active' => true,
        ]);
        $customer = Customer::factory()->create(['phone' => '012345678']);
        $event = $this->event($this->payload('Order please, phone 012 345 678'));

        app()->call([new ProcessMetaWebhook($event->id), 'handle']);
        app()->call([new ProcessMetaWebhook($event->id), 'handle']);

        $this->assertDatabaseCount('social_contacts', 1);
        $this->assertDatabaseCount('social_conversations', 1);
        $this->assertDatabaseCount('social_messages', 1);
        $contact = SocialContact::query()->sole();
        $this->assertNull($contact->customer_id);
        $this->assertSame($customer->id, $contact->suggested_customer_id);
        $encryptedBody = (string) SocialMessage::query()->sole()->getRawOriginal('body');
        $this->assertStringNotContainsString('012 345 678', $encryptedBody);
        $this->assertSame('processed', $event->refresh()->status->value);
    }

    public function test_seller_converts_linked_conversation_to_draft_using_server_price_without_stock_change(): void
    {
        $user = $this->userWithPermissions(['social.view', 'social.manage', 'orders.create']);
        $customer = Customer::factory()->create();
        $channel = SocialChannel::query()->create([
            'platform' => SocialPlatform::FacebookMessenger,
            'name' => 'Main Page',
            'external_id' => 'page-100',
            'active' => true,
        ]);
        $contact = SocialContact::query()->create([
            'social_channel_id' => $channel->id,
            'customer_id' => $customer->id,
            'external_id' => 'customer-100',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $conversation = SocialConversation::query()->create([
            'social_channel_id' => $channel->id,
            'social_contact_id' => $contact->id,
            'status' => 'open',
            'last_message_at' => now(),
        ]);
        $product = Product::factory()->create(['base_price' => '12.50']);
        app(AdjustStock::class)->execute($product, null, 5, StockMovementType::StockIn, $user);

        $this->actingAs($user)->post(route('social.inbox.draft-order.store', $conversation), [
            'currency' => 'USD',
            'discount' => '1.00',
            'delivery_fee' => '2.00',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'discount' => '0']],
        ])->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame('draft', $order->status->value);
        $this->assertSame('messenger', $order->source->value);
        $this->assertSame('26.00', $order->total);
        $this->assertSame('12.50', $order->items()->sole()->unit_price);
        $this->assertSame(5, $product->refresh()->stock_quantity);
        $this->assertSame($order->id, $conversation->refresh()->converted_order_id);
    }

    public function test_user_without_social_permission_cannot_open_inbox(): void
    {
        $user = $this->userWithPermissions(['orders.view']);

        $this->actingAs($user)->get(route('social.inbox.index'))->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function payload(string $text = 'I want one shirt'): array
    {
        return [
            'object' => 'page',
            'entry' => [[
                'id' => 'page-100',
                'messaging' => [[
                    'sender' => ['id' => 'customer-100'],
                    'recipient' => ['id' => 'page-100'],
                    'timestamp' => 1790755200000,
                    'message' => ['mid' => 'mid-100', 'text' => $text],
                ]],
            ]],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function event(array $payload): SocialWebhookEvent
    {
        return SocialWebhookEvent::query()->create([
            'platform' => SocialPlatform::FacebookMessenger,
            'event_id' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'status' => 'received',
            'payload' => $payload,
            'received_at' => now(),
        ]);
    }
}
