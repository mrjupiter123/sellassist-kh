<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Inventory\Actions\RecordStockCount;
use App\Domain\Inventory\Actions\ReviewStockCountDiscrepancy;
use App\Domain\Inventory\Models\StockCountDiscrepancyReview;
use App\Domain\Product\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockDiscrepancyReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_review_is_immutable_encrypted_and_does_not_change_stock(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $seller = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create(['name' => 'Review Shirt']);
        $count = app(RecordStockCount::class)->execute($product, null, 0, 4, $seller, (string) Str::uuid());
        $notes = 'Verified the shelf and movement.';

        $this->actingAs($admin)->post(route('inventory.discrepancies.review', $count), [
            'review_notes' => $notes,
        ])->assertRedirect(route('inventory.stock-counts.show', $count));

        $review = StockCountDiscrepancyReview::query()->sole();
        $this->assertSame($admin->id, $review->reviewed_by);
        $this->assertSame($notes, $review->review_notes);
        $this->assertNotSame($notes, DB::table('stock_count_discrepancy_reviews')->value('review_notes'));
        $this->assertSame(4, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 1);

        $this->actingAs($admin)->post(route('inventory.discrepancies.review', $count), [
            'review_notes' => $notes,
        ])->assertRedirect(route('inventory.stock-counts.show', $count));
        $this->assertDatabaseCount('stock_count_discrepancy_reviews', 1);

        $this->actingAs($admin)->post(route('inventory.discrepancies.review', $count), [
            'review_notes' => 'A different conclusion.',
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame($notes, $review->refresh()->review_notes);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_report_filters_pending_and_reviewed_discrepancies_and_excludes_no_change(): void
    {
        $admin = $this->userWithPermissions(['inventory.review', 'inventory.adjust']);
        $shirt = Product::factory()->create(['name' => 'Reviewable Shirt', 'sku' => 'REV-SHIRT']);
        $bag = Product::factory()->create(['name' => 'Reviewable Bag', 'sku' => 'REV-BAG']);
        $record = app(RecordStockCount::class);
        $pending = $record->execute($shirt, null, 0, 4, $admin, (string) Str::uuid());
        $unchanged = $record->execute($shirt, null, 4, 4, $admin, (string) Str::uuid());
        $reviewed = $record->execute($bag, null, 0, 2, $admin, (string) Str::uuid());
        app(ReviewStockCountDiscrepancy::class)->execute($reviewed, $admin, 'Count and adjustment checked.');
        $pending->forceFill(['created_at' => '2026-10-01 12:00:00'])->save();
        $unchanged->forceFill(['created_at' => '2026-10-01 13:00:00'])->save();
        $reviewed->forceFill(['created_at' => '2026-10-05 12:00:00'])->save();

        $this->actingAs($admin)->get(route('inventory.discrepancies.index', [
            'reviewed' => 'pending',
            'search' => 'REV-SHIRT',
            'created_from' => '2026-10-01',
            'created_to' => '2026-10-01',
        ]))->assertOk()->assertViewHas('counts', function ($counts) use ($pending): bool {
            return $counts->total() === 1 && $counts->items()[0]->id === $pending->id;
        })->assertViewHas('summary', ['total' => 1, 'reviewed' => 0, 'pending' => 1]);

        $this->actingAs($admin)->get(route('inventory.discrepancies.index', ['reviewed' => 'reviewed']))
            ->assertOk()->assertViewHas('counts', function ($counts) use ($reviewed): bool {
                return $counts->total() === 1 && $counts->items()[0]->id === $reviewed->id;
            })->assertViewHas('summary', ['total' => 2, 'reviewed' => 1, 'pending' => 1]);
    }

    public function test_staff_cannot_view_or_record_supervisor_reviews(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $product = Product::factory()->create();
        $count = app(RecordStockCount::class)->execute($product, null, 0, 2, $staff, (string) Str::uuid());
        app(ReviewStockCountDiscrepancy::class)->execute($count, $admin, 'Private supervisor note.');

        $this->actingAs($staff)->get(route('inventory.discrepancies.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('inventory.discrepancies.review', $count), [
            'review_notes' => 'Unauthorized review',
        ])->assertForbidden();
        $this->actingAs($staff)->get(route('inventory.stock-counts.show', $count))
            ->assertOk()->assertDontSeeText('Private supervisor note.');
        $this->assertDatabaseCount('stock_count_discrepancy_reviews', 1);
    }

    public function test_review_permission_can_open_count_details_without_adjust_permission(): void
    {
        $reviewer = $this->userWithPermissions(['inventory.review']);
        $seller = $this->userWithPermissions(['inventory.adjust']);
        $product = Product::factory()->create();
        $count = app(RecordStockCount::class)->execute($product, null, 0, 2, $seller, (string) Str::uuid());

        $this->actingAs($reviewer)->get(route('inventory.discrepancies.index'))->assertOk();
        $this->actingAs($reviewer)->get(route('inventory.stock-counts.show', $count))
            ->assertOk()->assertSeeText('Supervisor discrepancy review');
        $this->actingAs($reviewer)->get(route('inventory.stock-counts.index'))->assertForbidden();
    }

    public function test_no_difference_cannot_be_reviewed_and_invalid_filters_are_rejected(): void
    {
        $admin = $this->userWithPermissions(['inventory.review', 'inventory.adjust']);
        $product = Product::factory()->create();
        $count = app(RecordStockCount::class)->execute($product, null, 0, 0, $admin, (string) Str::uuid());

        $this->actingAs($admin)->get(route('inventory.discrepancies.index', [
            'reviewed' => 'invalid',
            'created_from' => '2026-10-10',
            'created_to' => '2026-10-01',
        ]))->assertRedirect(route('inventory.discrepancies.index'))->assertSessionHasErrors(['reviewed', 'created_to']);

        $this->actingAs($admin)->post(route('inventory.discrepancies.review', $count), [
            'review_notes' => 'This is too short',
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('stock_count_discrepancy_reviews', 0);
    }
}
