<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('social_ai_profile_release_alerts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('social_ai_profile_release_id')->unique();
            $table->string('status', 20)->index();
            $table->char('fingerprint', 64)->nullable();
            $table->text('reasons')->nullable();
            $table->timestamp('last_checked_at');
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('social_ai_profile_release_id', 'saipra_release_fk')
                ->references('id')->on('social_ai_profile_releases')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_ai_profile_release_alerts');
        Schema::dropIfExists('notifications');
    }
};
