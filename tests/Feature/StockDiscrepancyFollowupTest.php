<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Inventory\Actions\CompleteDiscrepancyFollowup;
use App\Domain\Inventory\Actions\CreateDiscrepancyFollowup;
use App\Domain\Inventory\Actions\RecordStockCount;
use App\Domain\Inventory\Actions\ReviewStockCountDiscrepancy;
use App\Domain\Inventory\Enums\DiscrepancyFollowupStatus;
use App\Domain\Inventory\Enums\StockDiscrepancyCause;
use App\Domain\Inventory\Exceptions\InvalidStockCountException;
use App\Domain\Inventory\Models\StockDiscrepancyFollowup;
use App\Domain\Product\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockDiscrepancyFollowupTest extends TestCase
{
    use RefreshDatabase;

    private function reviewedCount(User $supervisor, User $operator): array
    {
        $product = Product::factory()->create(['name' => 'Follow-up Shirt']);
        $count = app(RecordStockCount::class)->execute($product, null, 0, 3, $operator, (string) Str::uuid());
        $review = app(ReviewStockCountDiscrepancy::class)->execute($count, $supervisor, 'Counted and checked the shelf.', StockDiscrepancyCause::CountError);

        return [$product, $count, $review];
    }

    public function test_supervisor_assigns_followup_and_assignee_completes_without_stock_change(): void
    {
        $supervisor = $this->userWithPermissions(['inventory.review']);
        $operator = $this->userWithPermissions(['inventory.adjust']);
        [$product, $count, $review] = $this->reviewedCount($supervisor, $operator);

        $this->actingAs($supervisor)->get(route('inventory.stock-counts.show', $count))
            ->assertOk()->assertSeeText('Assign corrective follow-up');
        $this->actingAs($supervisor)->post(route('inventory.followups.store', $review), [
            'title' => 'Recheck receiving process',
            'assignee' => $operator->uuid,
            'due_on' => '2026-10-30',
        ])->assertRedirect();

        $followup = StockDiscrepancyFollowup::query()->sole();
        $this->assertSame($operator->id, $followup->assigned_to);
        $this->assertSame(DiscrepancyFollowupStatus::Open, $followup->status);
        $this->actingAs($operator)->get(route('inventory.followups.index'))->assertOk()->assertSeeText('Recheck receiving process');
        $this->actingAs($operator)->get(route('inventory.followups.show', $followup))->assertOk()
            ->assertDontSeeText('Counted and checked the shelf.');

        $this->actingAs($operator)->post(route('inventory.followups.complete', $followup), [
            'outcome_note' => 'Receiving checklist corrected.',
        ])->assertRedirect(route('inventory.followups.show', $followup));
        $this->assertSame(DiscrepancyFollowupStatus::Completed, $followup->refresh()->status);
        $this->assertSame('Receiving checklist corrected.', $followup->outcome_note);
        $this->assertNotSame($followup->outcome_note, DB::table('stock_discrepancy_followups')->value('outcome_note'));
        $this->assertSame(3, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('stock_counts', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('stock_count_discrepancy_reviews', 1);
    }

    public function test_only_assignee_or_supervisor_can_view_or_complete_and_staff_cannot_assign(): void
    {
        $supervisor = $this->userWithPermissions(['inventory.review']);
        $operator = $this->userWithPermissions(['inventory.adjust']);
        $other = $this->userWithPermissions(['inventory.adjust']);
        [, , $review] = $this->reviewedCount($supervisor, $operator);
        $followup = app(CreateDiscrepancyFollowup::class)->execute($review, $operator, $supervisor, 'Check receiving', null);

        $this->actingAs($other)->get(route('inventory.followups.index'))->assertOk()->assertDontSeeText('Check receiving');
        $this->actingAs($other)->get(route('inventory.followups.show', $followup))->assertForbidden();
        $this->actingAs($other)->post(route('inventory.followups.complete', $followup), ['outcome_note' => 'Not my task.'])->assertForbidden();
        $this->actingAs($operator)->post(route('inventory.followups.store', $review), [
            'title' => 'Wrong assignment', 'assignee' => $other->uuid,
        ])->assertForbidden();
        $this->actingAs($supervisor)->get(route('inventory.followups.index'))->assertOk()->assertSeeText('Check receiving');
        $this->assertSame(DiscrepancyFollowupStatus::Open, $followup->refresh()->status);
    }

    public function test_assignment_requires_active_operator_and_cannot_replace_existing_followup(): void
    {
        $supervisor = $this->userWithPermissions(['inventory.review']);
        $operator = $this->userWithPermissions(['inventory.adjust']);
        $inactive = $this->userWithPermissions(['inventory.adjust']);
        $inactive->update(['active' => false]);
        [, , $review] = $this->reviewedCount($supervisor, $operator);

        $this->actingAs($supervisor)->post(route('inventory.followups.store', $review), [
            'title' => 'Check receiving', 'assignee' => $inactive->uuid,
        ])->assertSessionHasErrors('assignee');

        $action = app(CreateDiscrepancyFollowup::class);
        $followup = $action->execute($review, $operator, $supervisor, 'Check receiving', '2026-10-30');
        $this->assertSame($followup->id, $action->execute($review, $operator, $supervisor, 'Check receiving', '2026-10-30')->id);

        $this->expectException(InvalidStockCountException::class);
        $action->execute($review, $operator, $supervisor, 'Changed action', '2026-10-30');
    }

    public function test_completion_retry_is_idempotent_but_outcome_cannot_be_changed(): void
    {
        $supervisor = $this->userWithPermissions(['inventory.review']);
        $operator = $this->userWithPermissions(['inventory.adjust']);
        [, , $review] = $this->reviewedCount($supervisor, $operator);
        $followup = app(CreateDiscrepancyFollowup::class)->execute($review, $operator, $supervisor, 'Check receiving', null);
        $action = app(CompleteDiscrepancyFollowup::class);
        $action->execute($followup, $operator, 'Checklist updated.');
        $completedAt = $followup->refresh()->completed_at;
        $action->execute($followup, $operator, 'Checklist updated.');
        $this->assertEquals($completedAt, $followup->refresh()->completed_at);

        $this->actingAs($operator)->post(route('inventory.followups.complete', $followup), [
            'outcome_note' => 'Different outcome.',
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('Checklist updated.', $followup->refresh()->outcome_note);
    }

    public function test_status_filter_and_input_validation(): void
    {
        $supervisor = $this->userWithPermissions(['inventory.review']);
        $operator = $this->userWithPermissions(['inventory.adjust']);
        [, , $review] = $this->reviewedCount($supervisor, $operator);
        $followup = app(CreateDiscrepancyFollowup::class)->execute($review, $operator, $supervisor, 'Check receiving', null);

        $this->actingAs($operator)->get(route('inventory.followups.index', ['status' => 'completed']))
            ->assertOk()->assertDontSeeText('Check receiving');
        $this->actingAs($operator)->get(route('inventory.followups.index', ['status' => 'invalid']))
            ->assertRedirect()->assertSessionHasErrors('status');
        $this->actingAs($operator)->post(route('inventory.followups.complete', $followup), [
            'outcome_note' => 'No',
        ])->assertSessionHasErrors('outcome_note');
    }
}
