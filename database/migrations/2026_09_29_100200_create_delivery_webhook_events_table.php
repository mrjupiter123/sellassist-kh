<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('delivery_provider_id')->constrained()->cascadeOnDelete();
            $table->string('event_id', 64);
            $table->string('status', 20)->index();
            $table->longText('payload');
            $table->text('error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['delivery_provider_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_webhook_events');
    }
};
