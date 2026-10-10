<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_ai_alert_preferences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique('saap_user_unique');
            $table->boolean('email_enabled')->default(false);
            $table->timestamps();

            $table->foreign('user_id', 'saap_user_fk')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_ai_alert_preferences');
    }
};
