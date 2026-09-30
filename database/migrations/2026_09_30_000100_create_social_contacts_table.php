<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('social_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('suggested_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('external_id', 191);
            $table->string('display_name')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();

            $table->unique(['social_channel_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_contacts');
    }
};
