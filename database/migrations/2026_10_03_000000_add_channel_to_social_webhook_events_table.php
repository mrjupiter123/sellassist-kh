<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_webhook_events', function (Blueprint $table): void {
            $table->foreignId('social_channel_id')
                ->nullable()
                ->after('uuid')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_webhook_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('social_channel_id');
        });
    }
};
