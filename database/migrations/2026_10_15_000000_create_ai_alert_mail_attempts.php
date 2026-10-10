<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_alert_mail_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('release_id')->nullable()->constrained('social_ai_profile_releases')->nullOnDelete();
            $table->string('type', 20);
            $table->string('status', 20);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'aama_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_alert_mail_attempts');
    }
};
