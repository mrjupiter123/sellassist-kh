<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_ai_profile_releases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('social_ai_extraction_profile_id');
            $table->unsignedBigInteger('previous_profile_id')->nullable();
            $table->unsignedBigInteger('approval_evaluation_run_id')->nullable();
            $table->string('type', 20)->index();
            $table->text('reason');
            $table->unsignedBigInteger('released_by')->nullable();
            $table->timestamp('released_at')->index();
            $table->timestamps();

            $table->foreign('social_ai_extraction_profile_id', 'saipr_profile_fk')
                ->references('id')->on('social_ai_extraction_profiles')->restrictOnDelete();
            $table->foreign('previous_profile_id', 'saipr_previous_fk')
                ->references('id')->on('social_ai_extraction_profiles')->nullOnDelete();
            $table->foreign('approval_evaluation_run_id', 'saipr_approval_run_fk')
                ->references('id')->on('social_ai_evaluation_runs')->nullOnDelete();
            $table->foreign('released_by', 'saipr_releaser_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        $now = now();
        foreach (DB::table('social_ai_extraction_profiles')->where('active', true)->get() as $profile) {
            DB::table('social_ai_profile_releases')->insert([
                'uuid' => (string) Str::uuid(),
                'social_ai_extraction_profile_id' => $profile->id,
                'previous_profile_id' => null,
                'approval_evaluation_run_id' => $profile->approval_evaluation_run_id,
                'type' => 'activation',
                'reason' => 'Active profile backfilled when release history was introduced.',
                'released_by' => $profile->activated_by,
                'released_at' => $profile->activated_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_ai_profile_releases');
    }
};
