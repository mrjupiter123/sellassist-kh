<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_ai_evaluation_datasets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->string('version', 40);
            $table->text('release_notes');
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('frozen_by')->nullable();
            $table->timestamp('frozen_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'version'], 'saed_name_version_unique');
            $table->foreign('created_by', 'saed_creator_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('frozen_by', 'saed_freezer_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('social_ai_evaluation_dataset_cases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('social_ai_evaluation_dataset_id');
            $table->unsignedBigInteger('social_ai_evaluation_case_id');
            $table->unsignedSmallInteger('position');

            $table->unique(
                ['social_ai_evaluation_dataset_id', 'social_ai_evaluation_case_id'],
                'saedc_dataset_case_unique',
            );
            $table->foreign('social_ai_evaluation_dataset_id', 'saedc_dataset_fk')
                ->references('id')->on('social_ai_evaluation_datasets')->cascadeOnDelete();
            $table->foreign('social_ai_evaluation_case_id', 'saedc_case_fk')
                ->references('id')->on('social_ai_evaluation_cases')->restrictOnDelete();
        });

        Schema::table('social_ai_evaluation_runs', function (Blueprint $table): void {
            $table->unsignedBigInteger('social_ai_evaluation_dataset_id')->nullable()->after('social_ai_extraction_profile_id');
            $table->foreign('social_ai_evaluation_dataset_id', 'saer_dataset_fk')
                ->references('id')->on('social_ai_evaluation_datasets')->nullOnDelete();
        });

        Schema::table('social_ai_extraction_profiles', function (Blueprint $table): void {
            $table->unsignedBigInteger('approval_baseline_run_id')->nullable()->after('approval_evaluation_run_id');
            $table->decimal('approval_score_delta', 6, 4)->nullable()->after('approval_baseline_run_id');
            $table->text('approval_release_notes')->nullable()->after('approval_score_delta');

            $table->foreign('approval_baseline_run_id', 'saep_baseline_run_fk')
                ->references('id')->on('social_ai_evaluation_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_ai_extraction_profiles', function (Blueprint $table): void {
            $table->dropForeign('saep_baseline_run_fk');
            $table->dropColumn(['approval_baseline_run_id', 'approval_score_delta', 'approval_release_notes']);
        });
        Schema::table('social_ai_evaluation_runs', function (Blueprint $table): void {
            $table->dropForeign('saer_dataset_fk');
            $table->dropColumn('social_ai_evaluation_dataset_id');
        });
        Schema::dropIfExists('social_ai_evaluation_dataset_cases');
        Schema::dropIfExists('social_ai_evaluation_datasets');
    }
};
