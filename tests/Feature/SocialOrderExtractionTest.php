<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Social\Enums\ConversationStatus;
use App\Domain\Social\Enums\MessageDirection;
use App\Domain\Social\Enums\MessageType;
use App\Domain\Social\Enums\OrderExtractionStatus;
use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Models\SocialChannel;
use App\Domain\Social\Models\SocialContact;
use App\Domain\Social\Models\SocialConversation;
use App\Domain\Social\Models\SocialMessage;
use App\Domain\Social\Models\SocialOrderExtraction;
use App\Jobs\ExtractSocialOrderSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SocialOrderExtractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'social.ai.enabled' => true,
            'social.ai.api_key' => 'test-key-never-sent-to-storage',
            'social.ai.model' => 'gpt-5.4-mini',
            'social.ai.base_url' => 'https://api.openai.com/v1',
        ]);
    }

    public function test_authorized_seller_can_queue_one_idempotent_extraction_for_current_messages(): void
    {
        Queue::fake();
        $user = $this->userWithPermissions(['social.extract']);
        $conversation = $this->conversationWithMessage('I want two black shirts. Phone 012345678.');

        $this->actingAs($user)
            ->post(route('social.inbox.order-extractions.store', $conversation))
            ->assertRedirect(route('social.inbox.show', $conversation))
            ->assertSessionHas('success');
        $this->actingAs($user)->post(route('social.inbox.order-extractions.store', $conversation))->assertRedirect();

        $this->assertDatabaseCount('social_order_extractions', 1);
        $extraction = SocialOrderExtraction::query()->sole();
        $this->assertSame(OrderExtractionStatus::Queued, $extraction->status);
        $this->assertSame(1, $extraction->message_count);
        $this->assertNotSame(
            json_encode([$conversation->messages()->value('id')]),
            $extraction->getRawOriginal('source_message_ids'),
        );
        Queue::assertPushed(ExtractSocialOrderSuggestion::class, 1);
    }

    public function test_user_without_extraction_permission_is_forbidden(): void
    {
        $user = $this->userWithPermissions(['social.view']);

        $this->actingAs($user)
            ->post(route('social.inbox.order-extractions.store', $this->conversationWithMessage('One item')))
            ->assertForbidden();

        $this->assertDatabaseCount('social_order_extractions', 0);
    }

    public function test_archived_conversation_cannot_be_extracted(): void
    {
        $user = $this->userWithPermissions(['social.extract']);
        $conversation = $this->conversationWithMessage('One item');
        $conversation->update(['status' => ConversationStatus::Archived]);

        $this->actingAs($user)
            ->post(route('social.inbox.order-extractions.store', $conversation))
            ->assertSessionHas('error', 'Suggestions can only be generated for an open, unconverted conversation.');

        $this->assertDatabaseCount('social_order_extractions', 0);
    }

    public function test_job_stores_encrypted_suggestions_and_resolves_only_valid_active_catalog_references(): void
    {
        $user = $this->userWithPermissions(['social.extract']);
        $conversation = $this->conversationWithMessage('My name is Dara. Two black shirts to Phnom Penh.');
        $product = Product::factory()->create(['name' => 'Oversize Shirt', 'base_price' => 15]);
        $variant = ProductVariant::factory()->for($product)->create(['color' => 'Black', 'size' => 'M']);
        $otherVariant = ProductVariant::factory()->create();
        $message = $conversation->messages()->sole();
        $extraction = $conversation->orderExtractions()->create([
            'status' => OrderExtractionStatus::Queued,
            'provider' => 'openai',
            'model' => 'gpt-5.4-mini',
            'input_hash' => hash('sha256', 'input'),
            'message_count' => 1,
            'source_message_ids' => [$message->id],
            'requested_by' => $user->id,
        ]);

        Http::fake(['api.openai.com/*' => Http::response([
            'id' => 'resp_test_123',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'customer_name' => 'Dara',
                        'phone' => '012345678',
                        'address' => 'Phnom Penh',
                        'province' => 'Phnom Penh',
                        'district' => null,
                        'commune' => null,
                        'notes' => null,
                        'overall_confidence' => 0.91,
                        'items' => [
                            [
                                'product_ref' => $product->uuid,
                                'variant_ref' => $variant->uuid,
                                'product_query' => 'black shirt',
                                'variant_query' => 'Black / M',
                                'quantity' => 2,
                                'confidence' => 0.94,
                            ],
                            [
                                'product_ref' => $product->uuid,
                                'variant_ref' => $otherVariant->uuid,
                                'product_query' => 'shirt',
                                'variant_query' => 'wrong product variant',
                                'quantity' => 1,
                                'confidence' => 0.4,
                            ],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ]],
            ]],
        ], 200)]);

        app()->call([new ExtractSocialOrderSuggestion($extraction->id), 'handle']);

        $extraction->refresh()->load('items');
        $this->assertSame(OrderExtractionStatus::Ready, $extraction->status);
        $this->assertSame('Dara', $extraction->customer_name);
        $this->assertSame($product->id, $extraction->items[0]->product_id);
        $this->assertSame($variant->id, $extraction->items[0]->product_variant_id);
        $this->assertSame($product->id, $extraction->items[1]->product_id);
        $this->assertNull($extraction->items[1]->product_variant_id);
        $this->assertStringNotContainsString('Dara', (string) $extraction->getRawOriginal('customer_name'));
        $this->assertStringNotContainsString('black shirt', (string) $extraction->getRawOriginal('result_payload'));
        $this->assertStringNotContainsString('black shirt', (string) $extraction->items[0]->getRawOriginal('product_query'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('payments', 0);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $context = $payload['input'][0]['content'][0]['text'];

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $payload['store'] === false
                && $payload['text']['format']['type'] === 'json_schema'
                && str_contains($context, 'My name is Dara')
                && ! str_contains($context, 'base_price')
                && ! str_contains($context, 'stock_quantity')
                && ! str_contains($context, 'cost');
        });
    }

    public function test_provider_failure_is_saved_without_leaking_api_key(): void
    {
        $user = $this->userWithPermissions(['social.extract']);
        $conversation = $this->conversationWithMessage('One item');
        $message = $conversation->messages()->sole();
        $extraction = $conversation->orderExtractions()->create([
            'status' => OrderExtractionStatus::Queued,
            'provider' => 'openai',
            'model' => 'gpt-5.4-mini',
            'input_hash' => hash('sha256', 'failure'),
            'message_count' => 1,
            'source_message_ids' => [$message->id],
            'requested_by' => $user->id,
        ]);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'test-key-never-sent-to-storage']], 401)]);

        try {
            app()->call([new ExtractSocialOrderSuggestion($extraction->id), 'handle']);
            $this->fail('The job should fail.');
        } catch (\DomainException) {
            // The queue records a safe domain failure and retries according to job policy.
        }

        $extraction->refresh();
        $this->assertSame(OrderExtractionStatus::Failed, $extraction->status);
        $this->assertStringNotContainsString('test-key-never-sent-to-storage', (string) $extraction->error);
        $this->assertDatabaseCount('social_order_extraction_items', 0);
    }

    private function conversationWithMessage(string $body): SocialConversation
    {
        $channel = SocialChannel::query()->create([
            'platform' => SocialPlatform::FacebookMessenger,
            'name' => 'Main Page',
            'external_id' => 'page-'.fake()->unique()->numberBetween(1000, 999999),
            'active' => true,
        ]);
        $contact = SocialContact::query()->create([
            'social_channel_id' => $channel->id,
            'external_id' => 'contact-'.fake()->unique()->numberBetween(1000, 999999),
            'display_name' => 'Social Customer',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $conversation = SocialConversation::query()->create([
            'social_channel_id' => $channel->id,
            'social_contact_id' => $contact->id,
            'status' => ConversationStatus::Open,
            'last_message_at' => now(),
            'last_inbound_at' => now(),
        ]);
        SocialMessage::query()->create([
            'social_conversation_id' => $conversation->id,
            'external_id' => 'mid-'.fake()->unique()->numberBetween(1000, 999999),
            'direction' => MessageDirection::Inbound,
            'type' => MessageType::Text,
            'body' => $body,
            'attachments' => [],
            'raw_payload' => [],
            'sent_at' => now(),
        ]);

        return $conversation;
    }
}
