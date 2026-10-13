<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Inventory\Actions\GetStockDiscrepancyTrends;
use App\Domain\Inventory\Actions\RecordStockCount;
use App\Domain\Inventory\Actions\ReviewStockCountDiscrepancy;
use App\Domain\Inventory\Enums\DiscrepancyTrendWindow;
use App\Domain\Inventory\Enums\StockDiscrepancyCause;
use App\Domain\Inventory\Models\StockCountDiscrepancyReview;
use App\Domain\Product\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockDiscrepancyTrendsTest extends TestCase
{
    use RefreshDatabase;

    public function test_trends_aggregate_recent_counts_without_exposing_review_notes(): void
    {
        Carbon::setTestNow('2026-10-13 12:00:00');

        try {
            $supervisor = $this->userWithPermissions(['inventory.adjust', 'inventory.review']);
            $shirt = Product::factory()->create(['name' => 'Trend Shirt']);
            $bag = Product::factory()->create(['name' => 'Trend Bag']);
            $record = app(RecordStockCount::class);
            $first = $record->execute($shirt, null, 0, 5, $supervisor, (string) Str::uuid());
            $second = $record->execute($shirt, null, 5, 3, $supervisor, (string) Str::uuid());
            $third = $record->execute($bag, null, 0, 4, $supervisor, (string) Str::uuid());
            $old = $record->execute($bag, null, 4, 6, $supervisor, (string) Str::uuid());
            $old->forceFill(['created_at' => '2026-08-01 12:00:00'])->save();

            app(ReviewStockCountDiscrepancy::class)->execute($first, $supervisor, 'Private notes not shown in trends.', StockDiscrepancyCause::CountError);
            app(ReviewStockCountDiscrepancy::class)->execute($third, $supervisor, 'Another private note.', StockDiscrepancyCause::Receiving);
            app(ReviewStockCountDiscrepancy::class)->execute($old, $supervisor, 'Historical private note.', StockDiscrepancyCause::DamageOrLoss);

            $report = app(GetStockDiscrepancyTrends::class)->execute(DiscrepancyTrendWindow::ThirtyDays);

            $this->assertSame(3, $report['total']);
            $this->assertSame(2, $report['reviewed']);
            $this->assertSame(1, $report['pending']);
            $this->assertCount(1, $report['daily']);
            $this->assertSame('2026-10-13', $report['daily']->first()->day);
            $this->assertSame(3, (int) $report['daily']->first()->discrepancy_count);
            $this->assertSame(11, (int) $report['daily']->first()->absolute_units);
            $this->assertSame($shirt->id, $report['topProducts']->first()->product_id);
            $this->assertSame(7, (int) $report['topProducts']->first()->absolute_units);
            $this->assertCount(2, $report['causes']);

            $this->actingAs($supervisor)->get(route('inventory.discrepancies.trends', ['window' => '30']))
                ->assertOk()
                ->assertSeeText('Trend Shirt')
                ->assertDontSeeText('Private notes not shown in trends.')
                ->assertDontSeeText('Historical private note.');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_legacy_uncategorized_reviews_are_visible_as_an_aggregate_bucket(): void
    {
        $supervisor = $this->userWithPermissions(['inventory.adjust', 'inventory.review']);
        $product = Product::factory()->create();
        $count = app(RecordStockCount::class)->execute($product, null, 0, 2, $supervisor, (string) Str::uuid());
        StockCountDiscrepancyReview::query()->create([
            'stock_count_id' => $count->id,
            'reviewed_by' => $supervisor->id,
            'review_notes' => 'Legacy review note.',
            'reviewed_at' => now(),
        ]);

        $report = app(GetStockDiscrepancyTrends::class)->execute(DiscrepancyTrendWindow::SevenDays);

        $this->assertSame(1, $report['reviewed']);
        $this->assertNull($report['causes']->sole()->root_cause);
        $this->actingAs($supervisor)->get(route('inventory.discrepancies.trends', ['window' => '7']))
            ->assertOk()->assertSeeText('Legacy review (uncategorized)')->assertDontSeeText('Legacy review note.');
    }

    public function test_invalid_window_and_missing_new_review_cause_are_rejected(): void
    {
        $supervisor = $this->userWithPermissions(['inventory.adjust', 'inventory.review']);
        $product = Product::factory()->create();
        $count = app(RecordStockCount::class)->execute($product, null, 0, 2, $supervisor, (string) Str::uuid());

        $this->actingAs($supervisor)->get(route('inventory.discrepancies.trends', ['window' => '365']))
            ->assertRedirect(route('inventory.discrepancies.trends'))->assertSessionHasErrors('window');
        $this->actingAs($supervisor)->post(route('inventory.discrepancies.review', $count), [
            'review_notes' => 'Checked physical count.',
        ])->assertSessionHasErrors('root_cause');
        $this->actingAs($supervisor)->post(route('inventory.discrepancies.review', $count), [
            'review_notes' => 'Checked physical count.',
            'root_cause' => 'invalid',
        ])->assertSessionHasErrors('root_cause');
        $this->assertDatabaseCount('stock_count_discrepancy_reviews', 0);
    }

    public function test_staff_cannot_open_supervisor_trends(): void
    {
        $staff = $this->userWithPermissions(['inventory.adjust']);

        $this->actingAs($staff)->get(route('inventory.discrepancies.trends'))->assertForbidden();
    }
}
