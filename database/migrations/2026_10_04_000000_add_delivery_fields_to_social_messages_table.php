<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_messages', function (Blueprint $table): void {
            $table->string('delivery_status', 20)->nullable()->after('direction')->index();
            $table->text('delivery_error')->nullable()->after('raw_payload');
            $table->foreignId('created_by')->nullable()->after('delivery_error')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['delivery_status', 'delivery_error']);
        });
    }
};
