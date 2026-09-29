<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Customer\Models\Customer;
use App\Domain\Delivery\Actions\CreateShipment;
use App\Domain\Delivery\Adapters\L192Adapter;
use App\Domain\Delivery\Enums\DeliveryAdapter;
use App\Domain\Delivery\Models\DeliveryProvider;
use App\Domain\Delivery\Models\DeliveryWebhookEvent;
use App\Domain\Delivery\Models\Shipment;
use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Order\Actions\ChangeOrderStatus;
use App\Domain\Order\Actions\CreateOrder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Product\Models\Product;
use App\Jobs\ProcessDeliveryWebhook;
use App\Jobs\SubmitShipmentToProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_l192_adapter_creates_package_from_server_snapshots(): void
    {
        [$user, $shipment] = $this->integratedShipment();
        config([
            'delivery.adapters.l192.base_url' => 'https://l192.test',
            'delivery.adapters.l192.token' => 'test-token',
        ]);
        Http::fake(['https://l192.test/v1/packages' => Http::response(['data' => ['package_id' => 635431]], 201)]);

        $result = app(L192Adapter::class)->create($shipment->load('order'));

        $this->assertSame('635431', $result->externalId);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://l192.test/v1/packages'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['customer_name'] === 'API Customer'
            && $request['cash'] === 20.0);
    }

    public function test_submission_endpoint_queues_provider_job(): void
    {
        [$user, $shipment] = $this->integratedShipment();
        Queue::fake();

        $this->actingAs($user)->post(route('delivery.shipments.integration.submit', $shipment))->assertRedirect();

        Queue::assertPushed(SubmitShipmentToProvider::class, fn ($job): bool => $job->shipmentId === $shipment->id);
    }

    public function test_submission_job_persists_external_reference_and_encrypted_audit(): void
    {
        [$user, $shipment] = $this->integratedShipment();
        config([
            'delivery.adapters.l192.base_url' => 'https://l192.test',
            'delivery.adapters.l192.token' => 'test-token',
        ]);
        Http::fake(['https://l192.test/v1/packages' => Http::response(['data' => ['package_id' => 778899]], 200)]);

        app()->call([new SubmitShipmentToProvider($shipment->id, $user->id), 'handle']);

        $shipment->refresh();
        $this->assertSame('778899', $shipment->external_id);
        $this->assertSame('synced', $shipment->integration_status->value);
        $this->assertDatabaseHas('delivery_integration_logs', [
            'shipment_id' => $shipment->id, 'operation' => 'create', 'status' => 'succeeded',
        ]);
        $rawPayload = (string) $shipment->integrationLogs()->sole()->getRawOriginal('response_payload');
        $this->assertStringNotContainsString('778899', $rawPayload);
    }

    public function test_webhook_requires_signature_and_is_idempotent(): void
    {
        [, $shipment] = $this->integratedShipment();
        config(['delivery.webhooks.secrets.l192' => 'webhook-secret']);
        Queue::fake();
        $payload = ['package_id' => 'PKG-1', 'status' => 'PICKUP'];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $route = route('api.delivery.webhooks.receive', $shipment->provider);

        $this->postJson($route, $payload)->assertUnauthorized();
        $headers = [
            'X-SellAssist-Signature' => hash_hmac('sha256', $raw, 'webhook-secret'),
            'X-Delivery-Event-Id' => 'event-123',
        ];
        $this->call('POST', $route, [], [], [], $this->serverHeaders($headers), $raw)->assertAccepted()->assertJson(['duplicate' => false]);
        $this->call('POST', $route, [], [], [], $this->serverHeaders($headers), $raw)->assertAccepted()->assertJson(['duplicate' => true]);

        $this->assertDatabaseCount('delivery_webhook_events', 1);
        Queue::assertPushed(ProcessDeliveryWebhook::class, 1);
    }

    public function test_provider_adapter_cannot_change_after_external_submission(): void
    {
        [$user, $shipment] = $this->integratedShipment();
        $shipment->update(['external_id' => 'PKG-LOCKED']);
        $provider = $shipment->provider;

        $this->actingAs($user)->from(route('delivery.providers.edit', $provider))->put(route('delivery.providers.update', $provider), [
            'name' => $provider->name,
            'code' => $provider->code,
            'adapter' => 'manual',
            'integration_enabled' => 0,
            'active' => 1,
        ])->assertSessionHasErrors('adapter');

        $this->assertSame(DeliveryAdapter::L192, $provider->refresh()->adapter);
    }

    public function test_webhook_job_advances_shipment_and_order_safely(): void
    {
        [, $shipment] = $this->integratedShipment();
        $shipment->update(['external_id' => 'PKG-1']);
        $event = DeliveryWebhookEvent::query()->create([
            'delivery_provider_id' => $shipment->delivery_provider_id,
            'event_id' => hash('sha256', 'delivered-event'),
            'status' => 'received',
            'payload' => ['package_id' => 'PKG-1', 'status' => 'DELIVERED'],
            'received_at' => now(),
        ]);

        app()->call([new ProcessDeliveryWebhook($event->id), 'handle']);

        $this->assertSame('delivered', $shipment->refresh()->status->value);
        $this->assertSame(OrderStatus::Completed, $shipment->order->refresh()->status);
        $this->assertSame('processed', $event->refresh()->status);
    }

    /** @return array{User, Shipment} */
    private function integratedShipment(): array
    {
        $user = $this->userWithPermissions();
        $customer = Customer::factory()->create([
            'name' => 'API Customer', 'phone' => '012345678', 'address' => 'Street 1', 'province' => 'Phnom Penh',
        ]);
        $product = Product::factory()->create(['base_price' => '10.00']);
        app(AdjustStock::class)->execute($product, null, 10, StockMovementType::StockIn, $user);
        $order = app(CreateOrder::class)->execute([
            'customer_id' => $customer->id, 'source' => 'manual', 'status' => 'new', 'currency' => 'USD',
            'discount' => '0', 'delivery_fee' => '0',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'discount' => '0']],
        ], $user);
        $order = app(ChangeOrderStatus::class)->execute($order, OrderStatus::Confirmed, $user);
        $order = app(ChangeOrderStatus::class)->execute($order, OrderStatus::Packed, $user);
        $provider = DeliveryProvider::factory()->create([
            'adapter' => DeliveryAdapter::L192,
            'integration_enabled' => true,
        ]);
        $shipment = app(CreateShipment::class)->execute($order, ['delivery_provider_id' => $provider->id], $user);

        return [$user, $shipment];
    }

    /** @param array<string, string> $headers
     * @return array<string, string>
     */
    private function serverHeaders(array $headers): array
    {
        return collect($headers)->mapWithKeys(fn (string $value, string $key): array => [
            'HTTP_'.strtoupper(str_replace('-', '_', $key)) => $value,
        ])->all();
    }
}
