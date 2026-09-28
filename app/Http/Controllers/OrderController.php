<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Customer\Enums\CustomerSource;
use App\Domain\Customer\Models\Customer;
use App\Domain\Order\Actions\CreateOrder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderStatusTransition;
use App\Domain\Payment\Enums\Currency;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Product\Models\Product;
use App\Http\Requests\Order\StoreOrderRequest;
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
        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'phone']);
        $products = Product::query()
            ->with(['variants' => fn ($query) => $query->where('active', true)->orderBy('color')->orderBy('size')])
            ->where('active', true)
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

        return view('orders.create', [
            'customers' => $customers,
            'catalog' => $catalog,
            'sources' => CustomerSource::cases(),
            'currencies' => Currency::cases(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    public function store(StoreOrderRequest $request, CreateOrder $action): RedirectResponse
    {
        $order = $action->execute($request->validated(), $request->user());

        return redirect()->route('orders.show', $order)->with('success', 'Order created successfully.');
    }

    public function show(Order $order, OrderStatusTransition $transitions): View
    {
        $order->load(['customer', 'items.product', 'items.variant', 'payments.creator', 'creator']);
        $paidTotal = (float) $order->payments->sum('amount');

        return view('orders.show', [
            'order' => $order,
            'nextStatuses' => $transitions->allowedFrom($order->status),
            'paymentMethods' => PaymentMethod::cases(),
            'paidTotal' => $paidTotal,
            'balance' => max(0, (float) $order->total - $paidTotal),
        ]);
    }
}
