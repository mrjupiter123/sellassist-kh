<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_providers', function (Blueprint $table): void {
            $table->string('adapter', 40)->default('manual')->index()->after('code');
            $table->boolean('integration_enabled')->default(false)->after('adapter');
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->string('external_id')->nullable()->after('tracking_number');
            $table->string('integration_status', 40)->default('manual')->index()->after('external_id');
            $table->text('integration_error')->nullable()->after('integration_status');
            $table->timestamp('last_synced_at')->nullable()->after('integration_error');
            $table->unique(['delivery_provider_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropUnique(['delivery_provider_id', 'external_id']);
            $table->dropColumn(['external_id', 'integration_status', 'integration_error', 'last_synced_at']);
        });
        Schema::table('delivery_providers', function (Blueprint $table): void {
            $table->dropIndex(['adapter']);
            $table->dropColumn(['adapter', 'integration_enabled']);
        });
    }
};
