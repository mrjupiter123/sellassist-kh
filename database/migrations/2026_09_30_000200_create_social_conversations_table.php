<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('social_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_contact_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('open')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->index();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['social_contact_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_conversations');
    }
};
