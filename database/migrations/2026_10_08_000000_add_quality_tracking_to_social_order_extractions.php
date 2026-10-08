<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('social_order_extractions', 'input_tokens')) {
            Schema::table('social_order_extractions', function (Blueprint $table): void {
                $table->unsignedInteger('input_tokens')->nullable()->after('provider_response_id');
            });
        }

        if (! Schema::hasColumn('social_order_extractions', 'output_tokens')) {
            Schema::table('social_order_extractions', function (Blueprint $table): void {
                $table->unsignedInteger('output_tokens')->nullable()->after('input_tokens');
            });
        }

        if (! Schema::hasColumn('social_order_extractions', 'total_tokens')) {
            Schema::table('social_order_extractions', function (Blueprint $table): void {
                $table->unsignedInteger('total_tokens')->nullable()->after('output_tokens');
            });
        }

        if (Schema::hasTable('social_order_extraction_reviews')) {
            return;
        }

        Schema::create('social_order_extraction_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('social_order_extraction_id');
            $table->string('verdict', 20)->index();
            $table->boolean('customer_fields_correct')->nullable();
            $table->boolean('item_matches_correct')->nullable();
            $table->boolean('quantities_correct')->nullable();
            $table->longText('notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->unique('social_order_extraction_id', 'soe_reviews_extraction_unique');
            $table->foreign('social_order_extraction_id', 'soe_reviews_extraction_fk')
                ->references('id')->on('social_order_extractions')->cascadeOnDelete();
            $table->foreign('reviewed_by', 'soe_reviews_reviewer_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_order_extraction_reviews');

        foreach (['input_tokens', 'output_tokens', 'total_tokens'] as $column) {
            if (Schema::hasColumn('social_order_extractions', $column)) {
                Schema::table('social_order_extractions', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
