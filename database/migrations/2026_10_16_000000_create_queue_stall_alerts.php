<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_stall_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('queue', 40)->unique();
            $table->string('status', 20);
            $table->unsignedInteger('stale_jobs')->default(0);
            $table->timestamp('stalled_since')->nullable();
            $table->timestamp('last_checked_at');
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_stall_alerts');
    }
};
