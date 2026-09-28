<?php

declare(strict_types=1);

namespace App\Domain\OrderReturn\Actions;

use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderCalculator;
use App\Domain\OrderActivity\Enums\OrderActivityType;
use App\Domain\OrderActivity\Services\RecordOrderActivity;
use App\Domain\OrderReturn\Exceptions\InvalidOrderReturnException;
use App\Domain\OrderReturn\Models\OrderReturn;
use App\Domain\OrderReturn\Models\OrderReturnItem;
use App\Domain\Payment\Actions\RecordRefund;
use App\Domain\Payment\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateOrderReturn
{
    public function __construct(
        private readonly AdjustStock $adjustStock,
        private readonly OrderCalculator $calculator,
        private readonly RecordRefund $recordRefund,
        private readonly RecordOrderActivity $recordActivity,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(Order $order, array $data, User $createdBy): OrderReturn
    {
        return DB::transaction(function () use ($order, $data, $createdBy): OrderReturn {
            $lockedOrder = Order::query()
                ->with(['items.product', 'items.variant'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->status !== OrderStatus::Completed) {
                throw new InvalidOrderReturnException('Only completed orders can receive product returns.');
            }

            $selectedItems = collect($data['items'])
                ->map(fn (array $item): array => [
                    ...$item,
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'restock' => (bool) ($item['restock'] ?? false),
                ])
                ->filter(fn (array $item): bool => $item['quantity'] > 0)
                ->values();

            if ($selectedItems->isEmpty()) {
                throw new InvalidOrderReturnException('Select at least one item quantity to return.');
            }

            $validatedItems = [];
            $returnedLineValue = 0;

            foreach ($selectedItems as $selected) {
                $orderItem = $lockedOrder->items->firstWhere('id', (int) $selected['order_item_id']);

                if ($orderItem === null) {
                    throw new InvalidOrderReturnException('A selected item does not belong to this order.');
                }

                $previouslyReturned = (int) OrderReturnItem::query()
                    ->where('order_item_id', $orderItem->id)
                    ->sum('quantity');
                $remaining = $orderItem->quantity - $previouslyReturned;

                if ($selected['quantity'] > $remaining) {
                    throw new InvalidOrderReturnException("Return quantity for {$orderItem->product_name} exceeds the remaining {$remaining} item(s).");
                }

                $returnedLineValue += intdiv(
                    $this->calculator->toMinorUnits($orderItem->line_total) * $selected['quantity'],
                    $orderItem->quantity,
                );
                $validatedItems[] = [$orderItem, $selected];
            }

            $orderReturn = OrderReturn::query()->create([
                'return_number' => 'PENDING-'.Str::uuid(),
                'order_id' => $lockedOrder->id,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'returned_at' => $data['returned_at'] ?? now(),
                'created_by' => $createdBy->id,
            ]);
            $orderReturn->update([
                'return_number' => 'RET-'.$orderReturn->created_at->format('Ymd').'-'.str_pad((string) $orderReturn->id, 4, '0', STR_PAD_LEFT),
            ]);

            foreach ($validatedItems as [$orderItem, $selected]) {
                $orderReturn->items()->create([
                    'order_item_id' => $orderItem->id,
                    'quantity' => $selected['quantity'],
                    'restock' => $selected['restock'],
                    'reason' => $selected['reason'] ?? null,
                ]);

                if ($selected['restock']) {
                    $this->adjustStock->execute(
                        $orderItem->product,
                        $orderItem->variant,
                        $selected['quantity'],
                        StockMovementType::Return,
                        $createdBy,
                        'order_return',
                        $orderReturn->id,
                        "Restocked from {$orderReturn->return_number}",
                    );
                }
            }

            $this->recordActivity->execute(
                $lockedOrder,
                OrderActivityType::ReturnRecorded,
                "Return {$orderReturn->return_number} recorded.",
                ['return_uuid' => $orderReturn->uuid, 'return_number' => $orderReturn->return_number],
                $createdBy,
            );

            if (! empty($data['refund_amount'])) {
                $subtotal = $this->calculator->toMinorUnits($lockedOrder->subtotal);
                $goodsAfterDiscount = $subtotal - $this->calculator->toMinorUnits($lockedOrder->discount);
                $maxRefund = $subtotal > 0
                    ? intdiv($returnedLineValue * $goodsAfterDiscount, $subtotal)
                    : 0;
                $requestedRefund = $this->calculator->toMinorUnits($data['refund_amount']);

                if ($requestedRefund > $maxRefund) {
                    throw new InvalidOrderReturnException('Refund exceeds the server-calculated refundable value of these returned items.');
                }

                $payment = Payment::query()
                    ->where('order_id', $lockedOrder->id)
                    ->findOrFail($data['payment_id']);

                $this->recordRefund->execute($lockedOrder, $payment, [
                    'amount' => $data['refund_amount'],
                    'refund_method' => $data['refund_method'],
                    'reference' => $data['refund_reference'] ?? null,
                    'notes' => 'Refund recorded with '.$orderReturn->return_number,
                    'refunded_at' => $data['returned_at'] ?? now(),
                ], $createdBy, $orderReturn);
            }

            return $orderReturn->load(['items.orderItem', 'refunds.payment', 'creator']);
        });
    }
}
