<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Order\Actions\ExportOrders;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class OrderExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_uses_worklist_filters_and_escapes_spreadsheet_formulas(): void
    {
        $seller = $this->userWithPermissions(['orders.view']);
        $matching = Order::factory()->create([
            'status' => OrderStatus::Confirmed,
            'customer_name' => '=HYPERLINK("https://example.invalid")',
            'customer_phone' => '012345678',
            'shipping_address' => 'Private address',
            'total' => '28.50',
        ]);
        $excluded = Order::factory()->create(['status' => OrderStatus::New]);

        $response = $this->actingAs($seller)->get(route('orders.export', ['status' => 'confirmed']));

        $response->assertOk()->assertDownload()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString($matching->order_number, $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString($excluded->order_number, $csv);
        $this->assertStringNotContainsString('012345678', $csv);
        $this->assertStringNotContainsString('Private address', $csv);
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_export_requires_order_view_permission(): void
    {
        $this->actingAs(User::factory()->create())->get(route('orders.export'))->assertForbidden();
    }

    public function test_export_rejects_invalid_filters_without_streaming(): void
    {
        $seller = $this->userWithPermissions(['orders.view']);

        $this->actingAs($seller)->get(route('orders.export', ['status' => 'invalid']))
            ->assertRedirect(route('orders.index'))
            ->assertSessionHasErrors('status');
    }

    public function test_oversized_export_returns_to_filtered_list_with_explanation(): void
    {
        $seller = $this->userWithPermissions(['orders.view']);
        $this->mock(ExportOrders::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('isWithinLimit')->once()->andReturn(false));

        $this->actingAs($seller)->get(route('orders.export', ['status' => 'confirmed']))
            ->assertRedirect(route('orders.index', ['status' => 'confirmed']))
            ->assertSessionHasErrors('export');
    }
}
