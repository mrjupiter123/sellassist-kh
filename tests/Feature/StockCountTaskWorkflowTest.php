<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Inventory\Actions\CompleteStockCountTask;
use App\Domain\Inventory\Actions\CreateStockCountTask;
use App\Domain\Inventory\Exceptions\InvalidStockCountException;
use App\Domain\Inventory\Models\StockCountTask;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockCountTaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_assign_and_complete_count_with_one_audited_movement(): void
    {
        $manager = $this->userWithPermissions(['inventory.adjust']);
        $assignee = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create(['name' => 'Count Me']);

        $this->actingAs($manager)->post(route('inventory.stock-count-tasks.store'), [
            'product' => $product->uuid,
            'assignee' => $assignee->uuid,
            'due_on' => '2026-10-20',
            'instructions' => 'Count the back shelf',
        ])->assertRedirect();

        $task = StockCountTask::query()->sole();
        $this->assertSame($assignee->id, $task->assigned_to);
        $this->assertSame($manager->id, $task->created_by);
        $this->actingAs($manager)->get(route('inventory.stock-count-tasks.index'))
            ->assertOk()->assertSeeText('1 open')->assertSeeText('Count Me');
        $this->actingAs($assignee)->get(route('inventory.stock-count-tasks.show', $task))
            ->assertOk()->assertSeeText('Count the back shelf');

        $token = (string) Str::uuid();
        $this->actingAs($assignee)->post(route('inventory.stock-count-tasks.complete', $task), [
            'uuid' => $token,
            'observed_quantity' => 0,
            'counted_quantity' => 5,
            'notes' => 'Five found',
        ])->assertRedirect(route('inventory.stock-count-tasks.show', $task));

        $this->assertNotNull($task->refresh()->stock_count_id);
        $this->assertNotNull($task->completed_at);
        $this->assertSame($token, $task->stockCount->uuid);
        $this->assertSame(5, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('stock_counts', 1);
        $this->assertDatabaseCount('stock_movements', 1);

        $this->actingAs($assignee)->post(route('inventory.stock-count-tasks.complete', $task), [
            'uuid' => $token,
            'observed_quantity' => 0,
            'counted_quantity' => 5,
            'notes' => 'Five found',
        ])->assertRedirect(route('inventory.stock-count-tasks.show', $task));
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(5, $product->refresh()->stock_quantity);

        $this->actingAs($assignee)->post(route('inventory.stock-count-tasks.complete', $task), [
            'uuid' => (string) Str::uuid(),
            'observed_quantity' => 5,
            'counted_quantity' => 8,
        ])->assertRedirect();
        $this->assertDatabaseCount('stock_counts', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(5, $product->refresh()->stock_quantity);
    }

    public function test_matching_count_completes_task_without_movement(): void
    {
        $assignee = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();
        $task = app(CreateStockCountTask::class)->execute($product, null, $assignee, $assignee);

        app(CompleteStockCountTask::class)->execute($task, $assignee, 0, 0, (string) Str::uuid());

        $this->assertNotNull($task->refresh()->stock_count_id);
        $this->assertSame(0, $task->stockCount->difference);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_stale_count_leaves_task_open_and_inventory_unchanged(): void
    {
        $assignee = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();
        $task = app(CreateStockCountTask::class)->execute($product, null, $assignee, $assignee);

        $this->expectException(InvalidStockCountException::class);
        try {
            app(CompleteStockCountTask::class)->execute($task, $assignee, 1, 2, (string) Str::uuid());
        } finally {
            $this->assertNull($task->refresh()->stock_count_id);
            $this->assertDatabaseCount('stock_counts', 0);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_unassigned_staff_cannot_complete_another_users_task(): void
    {
        $assignee = $this->userWithPermissions(['inventory.adjust']);
        $other = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();
        $task = app(CreateStockCountTask::class)->execute($product, null, $assignee, $assignee);

        $this->actingAs($other)->post(route('inventory.stock-count-tasks.complete', $task), [
            'uuid' => (string) Str::uuid(),
            'observed_quantity' => 0,
            'counted_quantity' => 4,
        ])->assertRedirect();
        $this->assertNull($task->refresh()->stock_count_id);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_administrator_can_complete_assigned_count(): void
    {
        $assignee = $this->userWithPermissions(['inventory.adjust']);
        $admin = $this->userWithPermissions(['inventory.adjust']);
        $admin->assignRole('admin');
        $product = Product::factory()->create();
        $task = app(CreateStockCountTask::class)->execute($product, null, $assignee, $admin);

        app(CompleteStockCountTask::class)->execute($task, $admin, 0, 2, (string) Str::uuid());

        $this->assertSame($admin->id, $task->refresh()->stockCount->created_by);
        $this->assertSame(2, $product->refresh()->stock_quantity);
    }

    public function test_invalid_variant_or_assignee_cannot_be_assigned(): void
    {
        $manager = $this->userWithPermissions(['inventory.adjust']);
        $unprivileged = $this->userWithPermissions(['inventory.view']);
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $other->id]);

        try {
            app(CreateStockCountTask::class)->execute($product, $variant, $manager, $manager);
            $this->fail('A variant from another product must be rejected.');
        } catch (InvalidStockCountException) {
            $this->assertDatabaseCount('stock_count_tasks', 0);
        }

        $this->expectException(InvalidStockCountException::class);
        app(CreateStockCountTask::class)->execute($product, null, $unprivileged, $manager);
    }

    public function test_variant_assignment_counts_only_the_selected_variant(): void
    {
        $assignee = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();
        $first = ProductVariant::factory()->create(['product_id' => $product->id]);
        $second = ProductVariant::factory()->create(['product_id' => $product->id]);
        $task = app(CreateStockCountTask::class)->execute($product, $first, $assignee, $assignee);

        app(CompleteStockCountTask::class)->execute($task, $assignee, 0, 6, (string) Str::uuid());

        $this->assertSame($first->id, $task->refresh()->stockCount->product_variant_id);
        $this->assertSame(6, $first->refresh()->stock_quantity);
        $this->assertSame(0, $second->refresh()->stock_quantity);
        $this->assertSame(0, $product->refresh()->stock_quantity);
    }

    public function test_task_pages_are_permission_gated(): void
    {
        $manager = $this->userWithPermissions(['inventory.adjust']);
        $viewer = $this->userWithPermissions(['inventory.view']);
        $product = Product::factory()->create();
        $task = app(CreateStockCountTask::class)->execute($product, null, $manager, $manager);

        $this->actingAs($viewer)->get(route('inventory.stock-count-tasks.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('inventory.stock-count-tasks.show', $task))->assertForbidden();
        $this->actingAs($viewer)->post(route('inventory.stock-count-tasks.complete', $task), [
            'uuid' => (string) Str::uuid(),
            'observed_quantity' => 0,
            'counted_quantity' => 1,
        ])->assertForbidden();
    }
}
