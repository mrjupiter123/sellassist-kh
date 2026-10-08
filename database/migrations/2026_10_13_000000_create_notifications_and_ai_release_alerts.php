<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('social_ai_profile_release_alerts')) {
            Schema::create('social_ai_profile_release_alerts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('social_ai_profile_release_id');
                $table->string('status', 20);
                $table->char('fingerprint', 64)->nullable();
                $table->text('reasons')->nullable();
                $table->timestamp('last_checked_at');
                $table->timestamp('last_notified_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('social_ai_profile_release_alerts', ['social_ai_profile_release_id'], 'unique')) {
            Schema::table('social_ai_profile_release_alerts', function (Blueprint $table): void {
                $table->unique('social_ai_profile_release_id', 'saipra_release_unique');
            });
        }

        if (! Schema::hasIndex('social_ai_profile_release_alerts', ['status'])) {
            Schema::table('social_ai_profile_release_alerts', function (Blueprint $table): void {
                $table->index('status', 'saipra_status_idx');
            });
        }

        $hasReleaseForeignKey = collect(Schema::getForeignKeys('social_ai_profile_release_alerts'))
            ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === ['social_ai_profile_release_id']);
        if (! $hasReleaseForeignKey) {
            Schema::table('social_ai_profile_release_alerts', function (Blueprint $table): void {
                $table->foreign('social_ai_profile_release_id', 'saipra_release_fk')
                    ->references('id')->on('social_ai_profile_releases')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_ai_profile_release_alerts');
        Schema::dropIfExists('notifications');
    }
};
