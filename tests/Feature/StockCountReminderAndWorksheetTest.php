<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Inventory\Actions\CompleteStockCountTask;
use App\Domain\Inventory\Actions\CreateStockCountCampaign;
use App\Domain\Inventory\Actions\CreateStockCountTask;
use App\Domain\Inventory\Actions\RemindOverdueStockCountTasks;
use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockCountReminderAndWorksheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_task_notifies_assignee_once_even_after_repeated_checks(): void
    {
        $assignee = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();
        $task = app(CreateStockCountTask::class)->execute($product, null, $assignee, $assignee, '2026-10-21');
        $action = app(RemindOverdueStockCountTasks::class);

        $this->assertSame(1, $action->execute('2026-10-22'));
        $this->assertSame(0, $action->execute('2026-10-22'));
        $this->assertSame(0, $action->execute('2026-10-23'));
        $this->assertDatabaseCount('stock_count_task_reminders', 1);
        $this->assertDatabaseCount('notifications', 1);
        $notification = $assignee->notifications()->sole();
        $this->assertSame('stock_count_task_overdue', $notification->data['kind']);
        $this->assertSame(route('inventory.stock-count-tasks.show', $task, false), $notification->data['url']);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_not_due_completed_or_unprivileged_assignments_receive_no_reminder(): void
    {
        $assignee = $this->userWithPermissions(['inventory.adjust']);
        $todayTask = app(CreateStockCountTask::class)->execute(Product::factory()->create(), null, $assignee, $assignee, '2026-10-22');
        $completed = app(CreateStockCountTask::class)->execute(Product::factory()->create(), null, $assignee, $assignee, '2026-10-20');
        app(CompleteStockCountTask::class)->execute($completed, $assignee, 0, 0, (string) Str::uuid());
        $revoked = app(CreateStockCountTask::class)->execute(Product::factory()->create(), null, $assignee, $assignee, '2026-10-20');
        $assignee->revokePermissionTo('inventory.adjust');

        $this->assertSame(0, app(RemindOverdueStockCountTasks::class)->execute('2026-10-22'));
        $this->assertDatabaseCount('stock_count_task_reminders', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertNull($todayTask->refresh()->stock_count_id);
        $this->assertNull($revoked->refresh()->stock_count_id);
    }

    public function test_worksheet_lists_open_items_without_recorded_quantities_and_requires_permission(): void
    {
        $manager = $this->userWithPermissions(['inventory.adjust']);
        $viewer = $this->userWithPermissions(['inventory.view']);
        $openProduct = Product::factory()->create(['name' => 'Open Worksheet Shirt', 'sku' => 'OPEN-SKU']);
        $completedProduct = Product::factory()->create(['name' => 'Completed Worksheet Bag']);
        $campaign = app(CreateStockCountCampaign::class)->execute('Blind shelf count', '2026-10-25', [
            ['product' => $openProduct->uuid, 'assignee' => $manager->uuid],
            ['product' => $completedProduct->uuid, 'assignee' => $manager->uuid],
        ], $manager);
        $completedTask = $campaign->tasks()->where('product_id', $completedProduct->id)->sole();
        app(CompleteStockCountTask::class)->execute($completedTask, $manager, 0, 7, (string) Str::uuid());

        $this->actingAs($manager)->get(route('inventory.stock-count-campaigns.worksheet', $campaign))
            ->assertOk()
            ->assertSeeText('Blind shelf count')
            ->assertSeeText('Open Worksheet Shirt')
            ->assertSeeText('OPEN-SKU')
            ->assertDontSeeText('Completed Worksheet Bag')
            ->assertDontSeeText('Expected stock');

        $this->actingAs($viewer)->get(route('inventory.stock-count-campaigns.worksheet', $campaign))->assertForbidden();
    }

    public function test_scheduler_command_uses_same_idempotent_action(): void
    {
        $assignee = $this->userWithPermissions(['inventory.adjust']);
        app(CreateStockCountTask::class)->execute(Product::factory()->create(), null, $assignee, $assignee, '2020-01-01');

        $this->artisan('inventory:remind-overdue-counts')->assertExitCode(0);
        $this->artisan('inventory:remind-overdue-counts')->assertExitCode(0);
        $this->assertDatabaseCount('stock_count_task_reminders', 1);
        $this->assertDatabaseCount('notifications', 1);
    }
}
