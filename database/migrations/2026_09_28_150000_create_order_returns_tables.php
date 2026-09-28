<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('return_number')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('returned_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'returned_at']);
        });

        Schema::create('order_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->boolean('restock')->default(true);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['order_return_id', 'order_item_id']);
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
        Schema::dropIfExists('order_returns');
    }
};
