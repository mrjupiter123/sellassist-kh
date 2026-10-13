<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_count_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->date('due_on')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('stock_count_tasks', function (Blueprint $table): void {
            $table->foreignId('stock_count_campaign_id')->nullable()->constrained('stock_count_campaigns')->restrictOnDelete();
            $table->index(['stock_count_campaign_id', 'stock_count_id'], 'sct_campaign_progress_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_count_tasks', function (Blueprint $table): void {
            $table->dropIndex('sct_campaign_progress_idx');
            $table->dropConstrainedForeignId('stock_count_campaign_id');
        });

        Schema::dropIfExists('stock_count_campaigns');
    }
};
