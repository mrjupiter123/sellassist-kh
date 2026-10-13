<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_discrepancy_followups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('review_id')->unique('sdf_review_unique')->constrained('stock_count_discrepancy_reviews')->restrictOnDelete();
            $table->string('title', 150);
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->date('due_on')->nullable();
            $table->string('status', 20);
            $table->text('outcome_note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['assigned_to', 'status', 'due_on'], 'sdf_assignee_status_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_discrepancy_followups');
    }
};
