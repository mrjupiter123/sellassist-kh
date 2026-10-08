<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_order_extractions', function (Blueprint $table): void {
            $table->unsignedInteger('input_tokens')->nullable()->after('provider_response_id');
            $table->unsignedInteger('output_tokens')->nullable()->after('input_tokens');
            $table->unsignedInteger('total_tokens')->nullable()->after('output_tokens');
        });

        Schema::create('social_order_extraction_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('social_order_extraction_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('verdict', 20)->index();
            $table->boolean('customer_fields_correct')->nullable();
            $table->boolean('item_matches_correct')->nullable();
            $table->boolean('quantities_correct')->nullable();
            $table->longText('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_order_extraction_reviews');

        Schema::table('social_order_extractions', function (Blueprint $table): void {
            $table->dropColumn(['input_tokens', 'output_tokens', 'total_tokens']);
        });
    }
};
