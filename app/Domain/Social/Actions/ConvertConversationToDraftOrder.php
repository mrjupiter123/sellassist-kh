<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Customer\Enums\CustomerSource;
use App\Domain\Order\Actions\CreateOrder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Social\Enums\ConversationStatus;
use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Exceptions\SocialConversationException;
use App\Domain\Social\Models\SocialConversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ConvertConversationToDraftOrder
{
    public function __construct(private readonly CreateOrder $createOrder) {}

    /** @param array<string, mixed> $data */
    public function execute(SocialConversation $conversation, array $data, User $actor): Order
    {
        return DB::transaction(function () use ($conversation, $data, $actor): Order {
            $locked = SocialConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            if ($locked->converted_order_id !== null) {
                return $locked->convertedOrder()->firstOrFail();
            }

            $customerId = $locked->contact()->value('customer_id');
            if ($customerId === null) {
                throw new SocialConversationException('Link or create a customer before converting this conversation.');
            }

            $locked->loadMissing('channel');
            $source = match ($locked->channel->platform) {
                SocialPlatform::FacebookMessenger => CustomerSource::Messenger,
                SocialPlatform::Telegram => CustomerSource::Telegram,
            };
            $order = $this->createOrder->execute([
                ...$data,
                'customer_id' => $customerId,
                'source' => $source->value,
                'status' => OrderStatus::Draft->value,
                'notes' => trim($locked->channel->platform->label().' conversation '.$locked->uuid."\n".($data['notes'] ?? '')),
            ], $actor);

            $locked->update([
                'status' => ConversationStatus::Converted,
                'converted_order_id' => $order->id,
                'converted_by' => $actor->id,
                'converted_at' => now(),
            ]);

            return $order;
        });
    }
}
