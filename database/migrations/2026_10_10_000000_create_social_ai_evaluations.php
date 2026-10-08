<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_ai_evaluation_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->string('locale', 10)->index();
            $table->longText('messages');
            $table->longText('catalog');
            $table->longText('expected_result');
            $table->boolean('active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by', 'saec_creator_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('social_ai_evaluation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('social_ai_extraction_profile_id');
            $table->string('status', 20)->index();
            $table->longText('case_ids');
            $table->unsignedSmallInteger('total_cases')->default(0);
            $table->unsignedSmallInteger('passed_cases')->default(0);
            $table->unsignedSmallInteger('failed_cases')->default(0);
            $table->decimal('score', 5, 4)->nullable();
            $table->unsignedInteger('total_tokens')->default(0);
            $table->text('error')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('social_ai_extraction_profile_id', 'saer_profile_fk')->references('id')->on('social_ai_extraction_profiles')->cascadeOnDelete();
            $table->foreign('requested_by', 'saer_requester_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('social_ai_evaluation_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('social_ai_evaluation_run_id');
            $table->unsignedBigInteger('social_ai_evaluation_case_id');
            $table->string('status', 20)->index();
            $table->decimal('score', 5, 4)->nullable();
            $table->boolean('passed')->default(false);
            $table->longText('actual_result')->nullable();
            $table->longText('differences')->nullable();
            $table->string('provider_response_id', 191)->nullable();
            $table->unsignedInteger('total_tokens')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['social_ai_evaluation_run_id', 'social_ai_evaluation_case_id'], 'sae_result_run_case_unique');
            $table->foreign('social_ai_evaluation_run_id', 'saeres_run_fk')->references('id')->on('social_ai_evaluation_runs')->cascadeOnDelete();
            $table->foreign('social_ai_evaluation_case_id', 'saeres_case_fk')->references('id')->on('social_ai_evaluation_cases')->cascadeOnDelete();
        });

        Schema::table('social_ai_extraction_profiles', function (Blueprint $table): void {
            $table->boolean('activation_eligible')->default(false)->after('active');
            $table->unsignedBigInteger('approved_by')->nullable()->after('activated_by');
            $table->unsignedBigInteger('approval_evaluation_run_id')->nullable()->after('approved_by');
            $table->timestamp('approved_at')->nullable()->after('approval_evaluation_run_id');

            $table->foreign('approved_by', 'saep_approver_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approval_evaluation_run_id', 'saep_approval_run_fk')->references('id')->on('social_ai_evaluation_runs')->nullOnDelete();
        });

        DB::table('social_ai_extraction_profiles')->where('active', true)->update(['activation_eligible' => true]);
    }

    public function down(): void
    {
        Schema::table('social_ai_extraction_profiles', function (Blueprint $table): void {
            $table->dropForeign('saep_approver_fk');
            $table->dropForeign('saep_approval_run_fk');
            $table->dropColumn(['activation_eligible', 'approved_by', 'approval_evaluation_run_id', 'approved_at']);
        });
        Schema::dropIfExists('social_ai_evaluation_results');
        Schema::dropIfExists('social_ai_evaluation_runs');
        Schema::dropIfExists('social_ai_evaluation_cases');
    }
};
