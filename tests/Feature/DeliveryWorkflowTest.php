<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Customer\Models\Customer;
use App\Domain\Delivery\Actions\ChangeShipmentStatus;
use App\Domain\Delivery\Actions\CreateShipment;
use App\Domain\Delivery\Actions\RecordCodRemittance;
use App\Domain\Delivery\Enums\CodStatus;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\InvalidCodRemittanceException;
use App\Domain\Delivery\Models\DeliveryProvider;
use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Actions\CreateOrder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Exceptions\OrderHasActiveShipmentException;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Actions\RecordPayment;
use App\Domain\Payment\Exceptions\InvalidPaymentAmountException;
use App\Domain\Product\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_delivery_provider_while_staff_cannot(): void
    {
        $admin = $this->userWithPermissions();
        $staff = User::factory()->create();
        $staff->givePermissionTo('delivery.view');

        $this->actingAs($admin)->post(route('delivery.providers.store'), [
            'name' => 'Local Express', 'code' => 'LEX', 'adapter' => 'manual',
            'integration_enabled' => 0, 'contact_phone' => '012345678', 'active' => 1,
        ])->assertRedirect(route('delivery.providers.index'));
        $this->assertDatabaseHas('delivery_providers', ['code' => 'LEX', 'active' => true]);

        $this->actingAs($staff)->get(route('delivery.providers.index'))->assertForbidden();
    }

    public function test_shipment_cod_is_calculated_from_server_side_outstanding_balance(): void
    {
        [$user, $order, $provider] = $this->confirmedOrder();
        app(RecordPayment::class)->execute($order, [
            'amount' => '5.00', 'currency' => 'USD', 'payment_method' => 'cash',
        ], $user);

        $this->actingAs($user)->post(route('orders.shipments.store', $order), [
            'delivery_provider_id' => $provider->id,
            'tracking_number' => 'TRACK-001',
            'cod_amount' => '0.01',
        ])->assertRedirect();

        $shipment = $order->shipments()->sole();
        $this->assertSame('15.00', $shipment->cod_amount);
        $this->assertSame(CodStatus::PendingCollection, $shipment->cod_status);
        $this->assertDatabaseHas('shipment_status_histories', ['shipment_id' => $shipment->id, 'status' => 'pending']);
        $this->actingAs($user)->get(route('delivery.shipments.show', $shipment))->assertOk()->assertSee('COD reconciliation');
        $this->actingAs($user)->get(route('delivery.shipments.label', $shipment))->assertOk()->assertSee('Delivery label');
    }

    public function test_pickup_and_delivery_synchronize_order_and_are_idempotent(): void
    {
        [$user, $order, $provider] = $this->confirmedOrder();
        app(ChangeOrderStatus::class)->execute($order, OrderStatus::Packed, $user);
        $shipment = app(CreateShipment::class)->execute($order, ['delivery_provider_id' => $provider->id], $user);
        $action = app(ChangeShipmentStatus::class);

        $shipment = $action->execute($shipment, ShipmentStatus::ReadyForPickup, $user);
        $shipment = $action->execute($shipment, ShipmentStatus::PickedUp, $user);
        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);

        $shipment = $action->execute($shipment, ShipmentStatus::InTransit, $user);
        $shipment = $action->execute($shipment, ShipmentStatus::Delivered, $user);
        $action->execute($shipment, ShipmentStatus::Delivered, $user);

        $this->assertSame(OrderStatus::Completed, $order->refresh()->status);
        $this->assertSame(CodStatus::Collected, $shipment->refresh()->cod_status);
        $this->assertSame(5, $shipment->histories()->count());
    }

    public function test_open_shipment_cod_is_resynchronized_after_a_direct_payment(): void
    {
        [$user, $order, $provider] = $this->confirmedOrder();
        $shipment = app(CreateShipment::class)->execute($order, ['delivery_provider_id' => $provider->id], $user);

        app(RecordPayment::class)->execute($order, [
            'amount' => '3.00', 'currency' => 'USD', 'payment_method' => 'aba',
        ], $user);

        $this->assertSame('17.00', $shipment->refresh()->cod_amount);
    }

    public function test_returned_shipment_cancels_order_and_restores_stock_once(): void
    {
        [$user, $order, $provider, $product] = $this->confirmedOrder();
        app(ChangeOrderStatus::class)->execute($order, OrderStatus::Packed, $user);
        $shipment = app(CreateShipment::class)->execute($order, ['delivery_provider_id' => $provider->id], $user);
        $action = app(ChangeShipmentStatus::class);
        $shipment = $action->execute($shipment, ShipmentStatus::ReadyForPickup, $user);
        $shipment = $action->execute($shipment, ShipmentStatus::PickedUp, $user);
        $shipment = $action->execute($shipment, ShipmentStatus::Returned, $user);
        $action->execute($shipment, ShipmentStatus::Returned, $user);

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(10, $product->refresh()->stock_quantity);
        $this->assertSame(CodStatus::Cancelled, $shipment->refresh()->cod_status);
    }

    public function test_order_cannot_be_cancelled_while_a_shipment_is_active(): void
    {
        [$user, $order, $provider] = $this->confirmedOrder();
        app(CreateShipment::class)->execute($order, ['delivery_provider_id' => $provider->id], $user);

        $this->expectException(OrderHasActiveShipmentException::class);
        app(ChangeOrderStatus::class)->execute($order, OrderStatus::Cancelled, $user);
    }

    public function test_partial_and_full_cod_remittances_create_linked_payments(): void
    {
        [$user, $order, $provider] = $this->confirmedOrder();
        app(ChangeOrderStatus::class)->execute($order, OrderStatus::Packed, $user);
        $shipment = app(CreateShipment::class)->execute($order, ['delivery_provider_id' => $provider->id], $user);
        $change = app(ChangeShipmentStatus::class);
        foreach ([ShipmentStatus::ReadyForPickup, ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::Delivered] as $status) {
            $shipment = $change->execute($shipment, $status, $user);
        }

        try {
            app(RecordPayment::class)->execute($order, [
                'amount' => '1.00', 'currency' => 'USD', 'payment_method' => 'cash',
            ], $user);
            $this->fail('A direct payment must not bypass collected COD reconciliation.');
        } catch (InvalidPaymentAmountException $exception) {
            $this->assertStringContainsString('COD remittance', $exception->getMessage());
        }

        $action = app(RecordCodRemittance::class);
        $first = $action->execute($shipment, ['amount' => '8.00', 'reference' => 'BATCH-1'], $user);
        $this->assertSame(CodStatus::PartiallyRemitted, $shipment->refresh()->cod_status);
        $this->assertSame($first->payment_id, $first->payment->id);

        $action->execute($shipment, ['amount' => '12.00', 'reference' => 'BATCH-2'], $user);
        $this->assertSame(CodStatus::Remitted, $shipment->refresh()->cod_status);
        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
        $this->assertSame(2, $shipment->remittances()->count());
    }

    public function test_cod_over_remittance_is_rejected_atomically(): void
    {
        [$user, $order, $provider] = $this->confirmedOrder();
        app(ChangeOrderStatus::class)->execute($order, OrderStatus::Packed, $user);
        $shipment = app(CreateShipment::class)->execute($order, ['delivery_provider_id' => $provider->id], $user);
        $change = app(ChangeShipmentStatus::class);
        foreach ([ShipmentStatus::ReadyForPickup, ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::Delivered] as $status) {
            $shipment = $change->execute($shipment, $status, $user);
        }

        $this->expectException(InvalidCodRemittanceException::class);
        try {
            app(RecordCodRemittance::class)->execute($shipment, ['amount' => '20.01'], $user);
        } finally {
            $this->assertDatabaseCount('cod_remittances', 0);
            $this->assertDatabaseCount('payments', 0);
        }
    }

    /** @return array{User, Order, DeliveryProvider, Product} */
    private function confirmedOrder(): array
    {
        $user = $this->userWithPermissions();
        $customer = Customer::factory()->create([
            'name' => 'Delivery Customer', 'phone' => '012345678', 'address' => 'Street 1', 'province' => 'Phnom Penh',
        ]);
        $product = Product::factory()->create(['base_price' => '10.00']);
        app(AdjustStock::class)->execute($product, null, 10, StockMovementType::StockIn, $user);
        $order = app(CreateOrder::class)->execute([
            'customer_id' => $customer->id,
            'source' => 'manual',
            'status' => 'new',
            'currency' => 'USD',
            'discount' => '0',
            'delivery_fee' => '0',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'discount' => '0']],
        ], $user);
        $order = app(ChangeOrderStatus::class)->execute($order, OrderStatus::Confirmed, $user);
        $provider = DeliveryProvider::factory()->create();

        return [$user, $order, $provider, $product];
    }
}
