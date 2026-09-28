<?php

declare(strict_types=1);

namespace App\Domain\Order\Actions;

use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderStatusTransition;
use App\Domain\OrderActivity\Enums\OrderActivityType;
use App\Domain\OrderActivity\Services\RecordOrderActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ChangeOrderStatus
{
    public function __construct(
        private readonly OrderStatusTransition $transitions,
        private readonly AdjustStock $adjustStock,
        private readonly RecordOrderActivity $recordActivity,
    ) {}

    public function execute(Order $order, OrderStatus $targetStatus, User $changedBy): Order
    {
        return DB::transaction(function () use ($order, $targetStatus, $changedBy): Order {
            $lockedOrder = Order::query()
                ->with(['items.product', 'items.variant'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->status === $targetStatus) {
                return $lockedOrder;
            }

            $previousStatus = $lockedOrder->status;
            $this->transitions->assertCanTransition($previousStatus, $targetStatus);

            if ($targetStatus === OrderStatus::Confirmed) {
                $this->moveOrderStock($lockedOrder, $changedBy, -1, StockMovementType::Order, 'Stock deducted on order confirmation');
            }

            if ($targetStatus === OrderStatus::Cancelled && $previousStatus->holdsStock()) {
                $this->moveOrderStock($lockedOrder, $changedBy, 1, StockMovementType::Return, 'Stock restored on order cancellation');
            }

            $attributes = ['status' => $targetStatus];
            $timestampColumn = match ($targetStatus) {
                OrderStatus::Confirmed => 'confirmed_at',
                OrderStatus::Packed => 'packed_at',
                OrderStatus::Shipped => 'shipped_at',
                OrderStatus::Completed => 'completed_at',
                OrderStatus::Cancelled => 'cancelled_at',
                default => null,
            };

            if ($timestampColumn !== null) {
                $attributes[$timestampColumn] = now();
            }

            $lockedOrder->update($attributes);

            $this->recordActivity->execute(
                $lockedOrder,
                OrderActivityType::StatusChanged,
                sprintf('Status changed from %s to %s.', $previousStatus->label(), $targetStatus->label()),
                ['from' => $previousStatus->value, 'to' => $targetStatus->value],
                $changedBy,
            );

            return $lockedOrder->refresh()->load(['customer', 'items.product', 'items.variant', 'payments']);
        });
    }

    private function moveOrderStock(
        Order $order,
        User $user,
        int $direction,
        StockMovementType $type,
        string $notes,
    ): void {
        foreach ($order->items as $item) {
            $this->adjustStock->execute(
                $item->product,
                $item->variant,
                $direction * $item->quantity,
                $type,
                $user,
                'order',
                $order->id,
                $notes,
            );
        }
    }
}
