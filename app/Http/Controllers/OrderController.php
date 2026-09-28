<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Customer\Enums\CustomerSource;
use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Actions\CreateOrder;
use App\Domain\Order\Actions\UpdateOrder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Exceptions\OrderCannotBeAmendedException;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderStatusTransition;
use App\Domain\Payment\Enums\Currency;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Product\Models\Product;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::query()
            ->with(['customer:id,uuid,name,phone', 'creator:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->query('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.addcslashes(trim((string) $request->query('search')), '%_\\').'%';
                $query->where(fn ($query) => $query->where('order_number', 'like', $term)
                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', $term)));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('orders.index', ['orders' => $orders, 'statuses' => OrderStatus::cases()]);
    }

    public function create(): View
    {
        return view('orders.create', [
            ...$this->formData(),
            'order' => null,
            'initialItems' => [['product_id' => '', 'product_variant_id' => '', 'quantity' => 1, 'discount' => '0']],
        ]);
    }

    public function edit(Order $order, UpdateOrder $action): View
    {
        if (! $action->canExecute($order)) {
            throw new OrderCannotBeAmendedException('Only draft or new orders can be amended.');
        }

        $order->load('items');

        return view('orders.create', [
            ...$this->formData($order),
            'order' => $order,
            'initialItems' => $order->items->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id ?? '',
                'quantity' => $item->quantity,
                'discount' => $item->discount,
            ])->values()->all(),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order, UpdateOrder $action): RedirectResponse
    {
        $action->execute($order, $request->validated());

        return redirect()->route('orders.show', $order)->with('success', 'Order amended successfully.');
    }

    /** @return array<string, mixed> */
    private function formData(?Order $order = null): array
    {
        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'phone']);
        $existingProductIds = $order?->items->pluck('product_id')->all() ?? [];
        $existingVariantIds = $order?->items->pluck('product_variant_id')->filter()->all() ?? [];
        $products = Product::query()
            ->with(['variants' => fn ($query) => $query
                ->where(fn ($query) => $query->where('active', true)->orWhereIn('id', $existingVariantIds))
                ->orderBy('color')
                ->orderBy('size')])
            ->where(fn ($query) => $query->where('active', true)->orWhereIn('id', $existingProductIds))
            ->orderBy('name')
            ->get();

        $catalog = $products->map(fn (Product $product): array => [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => $product->base_price,
            'stock' => $product->stock_quantity,
            'variants' => $product->variants->map(fn ($variant): array => [
                'id' => $variant->id,
                'name' => $variant->display_name,
                'sku' => $variant->sku,
                'price' => $variant->price ?? $product->base_price,
                'stock' => $variant->stock_quantity,
            ])->values(),
        ])->values();

        return [
            'customers' => $customers,
            'catalog' => $catalog,
            'sources' => CustomerSource::cases(),
            'currencies' => Currency::cases(),
            'paymentMethods' => PaymentMethod::cases(),
        ];
    }

    public function store(StoreOrderRequest $request, CreateOrder $action): RedirectResponse
    {
        $order = $action->execute($request->validated(), $request->user());

        return redirect()->route('orders.show', $order)->with('success', 'Order created successfully.');
    }

    public function show(Order $order, OrderStatusTransition $transitions): View
    {
        $order->load([
            'customer',
            'items.product',
            'items.variant',
            'items.returnItems',
            'payments.creator',
            'payments.refunds',
            'refunds.payment',
            'refunds.creator',
            'refunds.orderReturn',
            'returns.items.orderItem',
            'returns.refunds',
            'returns.creator',
            'creator',
        ]);
        $paidTotal = (float) $order->payments->sum('amount');
        $refundedTotal = (float) $order->refunds->sum('amount');
        $returnableItems = $order->items->map(fn ($item): array => [
            'item' => $item,
            'remaining' => $item->quantity - $item->returnItems->sum('quantity'),
        ])->filter(fn (array $entry): bool => $entry['remaining'] > 0)->values();
        $refundablePayments = $order->payments->map(fn ($payment): array => [
            'payment' => $payment,
            'remaining' => max(0, (float) $payment->amount - (float) $payment->refunds->sum('amount')),
        ])->filter(fn (array $entry): bool => $entry['remaining'] > 0)->values();

        return view('orders.show', [
            'order' => $order,
            'nextStatuses' => $transitions->allowedFrom($order->status),
            'paymentMethods' => PaymentMethod::cases(),
            'paidTotal' => $paidTotal,
            'balance' => max(0, (float) $order->total - $paidTotal),
            'refundedTotal' => $refundedTotal,
            'netPaid' => $paidTotal - $refundedTotal,
            'returnableItems' => $returnableItems,
            'refundablePayments' => $refundablePayments,
        ]);
    }
}
