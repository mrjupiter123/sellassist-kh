<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Inventory\Actions\CompleteStockCountTask;
use App\Domain\Inventory\Actions\CreateStockCountCampaign;
use App\Domain\Inventory\Actions\CreateStockCountTask;
use App\Domain\Inventory\Actions\GetStockCountCampaignProgress;
use App\Domain\Inventory\Exceptions\InvalidStockCountException;
use App\Domain\Inventory\Models\StockCountCampaign;
use App\Domain\Inventory\Models\StockCountTask;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockCountCampaignWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_batches_assignments_and_reports_progress_without_changing_stock_on_creation(): void
    {
        $manager = $this->userWithPermissions(['inventory.adjust']);
        $firstAssignee = $this->userWithPermissions(['inventory.adjust']);
        $secondAssignee = $this->userWithPermissions(['inventory.adjust']);
        $shirt = Product::factory()->create(['name' => 'Campaign Shirt']);
        $bag = Product::factory()->create(['name' => 'Campaign Bag']);

        $this->actingAs($manager)->post(route('inventory.stock-count-campaigns.store'), [
            'name' => 'October shelves',
            'due_on' => '2026-10-21',
            'assignments' => [
                ['product' => $shirt->uuid, 'assignee' => $firstAssignee->uuid],
                ['product' => $bag->uuid, 'assignee' => $secondAssignee->uuid],
            ],
        ])->assertRedirect();

        $campaign = StockCountCampaign::query()->sole();
        $this->assertSame('October shelves', $campaign->name);
        $this->assertDatabaseCount('stock_count_tasks', 2);
        $this->assertDatabaseCount('stock_counts', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(2, $campaign->tasks()->count());

        $firstTask = $campaign->tasks()->where('product_id', $shirt->id)->sole();
        app(CompleteStockCountTask::class)->execute($firstTask, $firstAssignee, 0, 3, (string) Str::uuid());

        $progress = app(GetStockCountCampaignProgress::class)->execute($campaign);
        $this->assertSame(2, $progress['total']);
        $this->assertSame(1, $progress['completed']);
        $this->assertSame(1, $progress['open']);
        $this->assertSame(1, $progress['discrepancies']);
        $this->assertCount(2, $progress['byAssignee']);
        $this->actingAs($manager)->get(route('inventory.stock-count-campaigns.show', $campaign))
            ->assertOk()->assertSeeText('October shelves')->assertSeeText('Campaign Shirt');
        $this->actingAs($manager)->get(route('inventory.stock-count-campaigns.index'))
            ->assertOk()->assertSeeText('1 / 2');
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_duplicate_item_rolls_back_entire_campaign(): void
    {
        $manager = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();
        $assignments = [
            ['product' => $product->uuid, 'assignee' => $manager->uuid],
            ['product' => $product->uuid, 'assignee' => $manager->uuid],
        ];

        $this->expectException(InvalidStockCountException::class);
        try {
            app(CreateStockCountCampaign::class)->execute('Duplicated', null, $assignments, $manager);
        } finally {
            $this->assertDatabaseCount('stock_count_campaigns', 0);
            $this->assertDatabaseCount('stock_count_tasks', 0);
        }
    }

    public function test_variant_campaign_assignment_uses_variant_and_rejects_wrong_product(): void
    {
        $manager = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $other = Product::factory()->create();

        $campaign = app(CreateStockCountCampaign::class)->execute('Variants', null, [
            ['product' => $product->uuid, 'variant' => $variant->uuid, 'assignee' => $manager->uuid],
        ], $manager);
        $this->assertSame($variant->id, $campaign->tasks()->sole()->product_variant_id);

        $this->expectException(InvalidStockCountException::class);
        try {
            app(CreateStockCountCampaign::class)->execute('Wrong variant', null, [
                ['product' => $other->uuid, 'variant' => $variant->uuid, 'assignee' => $manager->uuid],
            ], $manager);
        } finally {
            $this->assertDatabaseCount('stock_count_campaigns', 1);
            $this->assertDatabaseCount('stock_count_tasks', 1);
        }
    }

    public function test_existing_standalone_tasks_remain_unattached(): void
    {
        $manager = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();
        $task = app(CreateStockCountTask::class)->execute($product, null, $manager, $manager);

        $this->assertNull($task->stock_count_campaign_id);
        $this->actingAs($manager)->get(route('inventory.stock-count-tasks.show', $task))->assertOk();
    }

    public function test_campaign_routes_validate_input_and_require_permission(): void
    {
        $manager = $this->userWithPermissions(['inventory.adjust']);
        $viewer = $this->userWithPermissions(['inventory.view']);
        $product = Product::factory()->create();
        $campaign = app(CreateStockCountCampaign::class)->execute('Secure', null, [
            ['product' => $product->uuid, 'assignee' => $manager->uuid],
        ], $manager);

        $this->actingAs($viewer)->get(route('inventory.stock-count-campaigns.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('inventory.stock-count-campaigns.show', $campaign))->assertForbidden();
        $this->actingAs($viewer)->post(route('inventory.stock-count-campaigns.store'), [])->assertForbidden();

        $this->actingAs($manager)->post(route('inventory.stock-count-campaigns.store'), [
            'name' => '',
            'assignments' => [['product' => 'invalid', 'assignee' => 'invalid']],
        ])->assertSessionHasErrors(['name', 'assignments.0.product', 'assignments.0.assignee']);
        $this->assertSame(1, StockCountTask::query()->count());
    }
}
