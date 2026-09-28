<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_return_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->string('refund_method', 30);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('refunded_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'refunded_at']);
            $table->index(['payment_id', 'refunded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
