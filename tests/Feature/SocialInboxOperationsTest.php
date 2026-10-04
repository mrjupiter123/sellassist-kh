<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Order\Models\Order;
use App\Domain\Social\Actions\IngestSocialMessage;
use App\Domain\Social\Data\InboundSocialMessage;
use App\Domain\Social\Enums\ConversationStatus;
use App\Domain\Social\Enums\MessageType;
use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Models\SocialChannel;
use App\Domain\Social\Models\SocialContact;
use App\Domain\Social\Models\SocialConversation;
use App\Domain\Social\Models\SocialReplyTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialInboxOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_state_is_per_user_and_new_inbound_message_makes_conversation_unread_again(): void
    {
        $firstUser = $this->userWithPermissions(['social.view']);
        $secondUser = $this->userWithPermissions(['social.view']);
        $channel = $this->channel();
        $this->ingest($channel, 'mid-1', CarbonImmutable::now()->subMinute());
        $conversation = SocialConversation::query()->sole();

        $this->assertTrue($conversation->isUnreadFor($firstUser));
        $this->assertTrue($conversation->isUnreadFor($secondUser));

        $this->actingAs($firstUser)->get(route('social.inbox.show', $conversation))->assertOk();
        $conversation->unsetRelation('readReceipts')->refresh();
        $this->assertFalse($conversation->isUnreadFor($firstUser));
        $this->assertTrue($conversation->isUnreadFor($secondUser));

        $this->ingest($channel, 'mid-2', CarbonImmutable::now()->addMinute());
        $conversation->unsetRelation('readReceipts')->refresh();
        $this->assertTrue($conversation->isUnreadFor($firstUser));

        $this->actingAs($firstUser)
            ->get(route('social.inbox.index', ['unread' => 1]))
            ->assertOk()
            ->assertSeeText('Social Customer');
    }

    public function test_user_can_explicitly_mark_conversation_unread(): void
    {
        $user = $this->userWithPermissions(['social.view']);
        $conversation = $this->conversation();

        $this->actingAs($user)->get(route('social.inbox.show', $conversation))->assertOk();
        $this->assertDatabaseHas('social_conversation_reads', [
            'social_conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('social.inbox.read.destroy', $conversation))
            ->assertRedirect(route('social.inbox.index'));

        $this->assertDatabaseMissing('social_conversation_reads', [
            'social_conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_manager_can_assign_and_unassign_conversation_to_eligible_staff(): void
    {
        $manager = $this->userWithPermissions(['social.manage']);
        $staff = $this->userWithPermissions(['social.view']);
        $conversation = $this->conversation();

        $this->actingAs($manager)
            ->patch(route('social.inbox.assignment.update', $conversation), ['assigned_to' => $staff->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $conversation->refresh();
        $this->assertSame($staff->id, $conversation->assigned_to);
        $this->assertSame($manager->id, $conversation->assigned_by);
        $this->assertNotNull($conversation->assigned_at);

        $this->actingAs($manager)
            ->patch(route('social.inbox.assignment.update', $conversation), ['assigned_to' => null])
            ->assertRedirect();
        $this->assertNull($conversation->refresh()->assigned_to);
    }

    public function test_assignment_rejects_ineligible_staff_and_unauthorized_actor(): void
    {
        $manager = $this->userWithPermissions(['social.manage']);
        $unauthorized = $this->userWithPermissions(['orders.view']);
        $ineligible = User::factory()->create();
        $conversation = $this->conversation();

        $this->actingAs($manager)
            ->patch(route('social.inbox.assignment.update', $conversation), ['assigned_to' => $ineligible->id])
            ->assertSessionHas('error', 'Conversations can only be assigned to active users with social inbox access.');
        $this->assertNull($conversation->refresh()->assigned_to);

        $this->actingAs($unauthorized)
            ->patch(route('social.inbox.assignment.update', $conversation), ['assigned_to' => $manager->id])
            ->assertForbidden();
    }

    public function test_manager_can_archive_and_reopen_conversation_through_controlled_transition(): void
    {
        $manager = $this->userWithPermissions(['social.manage']);
        $conversation = $this->conversation();

        $this->actingAs($manager)
            ->patch(route('social.inbox.status.update', $conversation), ['status' => ConversationStatus::Archived->value])
            ->assertRedirect();
        $conversation->refresh();
        $this->assertSame(ConversationStatus::Archived, $conversation->status);
        $this->assertSame($manager->id, $conversation->archived_by);
        $this->assertNotNull($conversation->archived_at);

        $this->actingAs($manager)
            ->patch(route('social.inbox.status.update', $conversation), ['status' => ConversationStatus::Open->value])
            ->assertRedirect();
        $conversation->refresh();
        $this->assertSame(ConversationStatus::Open, $conversation->status);
        $this->assertNull($conversation->archived_at);
        $this->assertNull($conversation->archived_by);
    }

    public function test_reopening_archived_converted_conversation_preserves_converted_state(): void
    {
        $manager = $this->userWithPermissions(['social.manage']);
        $conversation = $this->conversation();
        $order = Order::factory()->create();
        $conversation->update([
            'status' => ConversationStatus::Converted,
            'converted_order_id' => $order->id,
            'converted_by' => $manager->id,
            'converted_at' => now(),
        ]);

        $this->actingAs($manager)
            ->patch(route('social.inbox.status.update', $conversation), ['status' => ConversationStatus::Archived->value])
            ->assertRedirect();
        $this->actingAs($manager)
            ->patch(route('social.inbox.status.update', $conversation), ['status' => ConversationStatus::Open->value])
            ->assertRedirect();

        $this->assertSame(ConversationStatus::Converted, $conversation->refresh()->status);
        $this->assertSame($order->id, $conversation->converted_order_id);
    }

    public function test_manager_can_create_update_and_disable_encrypted_reply_template(): void
    {
        $manager = $this->userWithPermissions(['social.manage']);

        $this->actingAs($manager)
            ->post(route('social.reply-templates.store'), [
                'title' => 'Order ready',
                'body' => 'Your order is ready for delivery.',
                'active' => '1',
            ])
            ->assertRedirect();

        $template = SocialReplyTemplate::query()->sole();
        $this->assertSame('Your order is ready for delivery.', $template->body);
        $this->assertStringNotContainsString('Your order is ready for delivery.', (string) $template->getRawOriginal('body'));
        $this->assertSame($manager->id, $template->created_by);

        $this->actingAs($manager)
            ->put(route('social.reply-templates.update', $template), [
                'title' => 'Order ready',
                'body' => 'Updated reply.',
            ])
            ->assertRedirect();

        $template->refresh();
        $this->assertSame('Updated reply.', $template->body);
        $this->assertFalse($template->active);
        $this->assertSame($manager->id, $template->updated_by);
    }

    public function test_active_template_is_available_to_reply_staff_but_management_requires_permission(): void
    {
        $manager = $this->userWithPermissions(['social.manage']);
        $staff = $this->userWithPermissions(['social.view', 'social.reply']);
        SocialReplyTemplate::query()->create([
            'title' => 'Greeting',
            'body' => 'Hello from SellAssist.',
            'active' => true,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        $this->actingAs($staff)
            ->get(route('social.inbox.show', $this->conversation()))
            ->assertOk()
            ->assertSeeText('Greeting');
        $this->actingAs($staff)->get(route('social.reply-templates.index'))->assertForbidden();
    }

    private function channel(): SocialChannel
    {
        return SocialChannel::query()->create([
            'platform' => SocialPlatform::FacebookMessenger,
            'name' => 'Main Page',
            'external_id' => 'page-100',
            'active' => true,
        ]);
    }

    private function conversation(): SocialConversation
    {
        $channel = $this->channel();
        $contact = SocialContact::query()->create([
            'social_channel_id' => $channel->id,
            'external_id' => 'customer-100',
            'display_name' => 'Social Customer',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        return SocialConversation::query()->create([
            'social_channel_id' => $channel->id,
            'social_contact_id' => $contact->id,
            'status' => ConversationStatus::Open,
            'last_message_at' => now(),
            'last_inbound_at' => now(),
        ]);
    }

    private function ingest(SocialChannel $channel, string $messageId, CarbonImmutable $sentAt): void
    {
        app(IngestSocialMessage::class)->execute(new InboundSocialMessage(
            platform: SocialPlatform::FacebookMessenger,
            channelExternalId: $channel->external_id,
            contactExternalId: 'customer-100',
            messageExternalId: $messageId,
            contactDisplayName: 'Social Customer',
            type: MessageType::Text,
            body: 'Hello',
            attachments: [],
            sentAt: $sentAt,
            rawPayload: ['message_id' => $messageId],
        ));
    }
}
