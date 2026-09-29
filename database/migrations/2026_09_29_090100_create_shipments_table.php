<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('shipment_number')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('delivery_provider_id')->constrained()->restrictOnDelete();
            $table->string('tracking_number')->nullable()->index();
            $table->string('status', 40)->index();
            $table->decimal('cod_amount', 15, 2)->default(0);
            $table->string('cod_status', 40)->index();
            $table->char('currency', 3);
            $table->string('recipient_name');
            $table->string('recipient_phone', 30)->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->unique(['delivery_provider_id', 'tracking_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
