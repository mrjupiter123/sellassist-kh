<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Delivery\Enums\CodStatus;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Models\Shipment;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\Currency;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = today();
        $orders = Order::query();

        $metrics = [
            'orders_today' => (clone $orders)->whereDate('created_at', $today)->count(),
            'confirmed_today' => (clone $orders)->whereDate('confirmed_at', $today)->count(),
            'completed_today' => (clone $orders)->whereDate('completed_at', $today)->count(),
            'cancelled_today' => (clone $orders)->whereDate('cancelled_at', $today)->count(),
        ];

        $salesToday = (clone $orders)
            ->select('currency')
            ->selectRaw('SUM(total) as amount')
            ->whereDate('completed_at', $today)
            ->groupBy('currency')
            ->pluck('amount', 'currency');
        $metrics['sales_today'] = collect(Currency::cases())->mapWithKeys(
            fn (Currency $currency): array => [$currency->value => (float) ($salesToday[$currency->value] ?? 0)],
        );

        $openOrders = Order::query()
            ->select(['id', 'total', 'currency'])
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->where('payment_status', '!=', PaymentStatus::Paid->value)
            ->withSum('payments', 'amount')
            ->get();
        $metrics['unpaid_amount'] = collect(Currency::cases())->mapWithKeys(function (Currency $currency) use ($openOrders): array {
            $amount = $openOrders
                ->where('currency', $currency)
                ->sum(fn (Order $order): float => max(0, (float) $order->total - (float) ($order->payments_sum_amount ?? 0)));

            return [$currency->value => $amount];
        });

        $metrics['active_shipments'] = Shipment::query()
            ->whereNotIn('status', [ShipmentStatus::Delivered->value, ShipmentStatus::Returned->value, ShipmentStatus::Cancelled->value])
            ->count();
        $openCod = Shipment::query()
            ->select(['id', 'cod_amount', 'currency'])
            ->whereIn('cod_status', [CodStatus::Collected->value, CodStatus::PartiallyRemitted->value])
            ->withSum('remittances', 'amount')
            ->get();
        $metrics['cod_outstanding'] = collect(Currency::cases())->mapWithKeys(function (Currency $currency) use ($openCod): array {
            $amount = $openCod
                ->where('currency', $currency)
                ->sum(fn (Shipment $shipment): float => max(0, (float) $shipment->cod_amount - (float) ($shipment->remittances_sum_amount ?? 0)));

            return [$currency->value => $amount];
        });

        $lowStockProducts = Product::query()
            ->doesntHave('variants')
            ->where('active', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->limit(8)
            ->get();

        $lowStockVariants = ProductVariant::query()
            ->with('product:id,uuid,name')
            ->where('active', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->limit(8)
            ->get();

        $recentOrders = Order::query()
            ->with(['customer:id,uuid,name', 'creator:id,name'])
            ->latest()
            ->limit(10)
            ->get();

        return view('dashboard.index', compact('metrics', 'lowStockProducts', 'lowStockVariants', 'recentOrders'));
    }
}
