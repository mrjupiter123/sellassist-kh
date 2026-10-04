<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_order_extractions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('social_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->index();
            $table->string('provider', 30)->default('openai');
            $table->string('model', 100);
            $table->string('input_hash', 64);
            $table->unsignedSmallInteger('message_count');
            $table->longText('source_message_ids');
            $table->longText('customer_name')->nullable();
            $table->longText('phone')->nullable();
            $table->longText('address')->nullable();
            $table->longText('province')->nullable();
            $table->longText('district')->nullable();
            $table->longText('commune')->nullable();
            $table->longText('notes')->nullable();
            $table->decimal('overall_confidence', 5, 4)->nullable();
            $table->string('provider_response_id', 191)->nullable();
            $table->longText('result_payload')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['social_conversation_id', 'created_at']);
            $table->index(['social_conversation_id', 'input_hash']);
        });

        Schema::create('social_order_extraction_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('social_order_extraction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('product_query');
            $table->longText('variant_query')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('confidence', 5, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_order_extraction_items');
        Schema::dropIfExists('social_order_extractions');
    }
};
