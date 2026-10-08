<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_ai_extraction_profiles')) {
            Schema::create('social_ai_extraction_profiles', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name', 100);
                $table->string('version', 50);
                $table->string('model', 100);
                $table->longText('instructions');
                $table->boolean('active')->default(false)->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('activated_by')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamps();

                $table->unique(['name', 'version'], 'saep_name_version_unique');
                $table->foreign('created_by', 'saep_creator_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('activated_by', 'saep_activator_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('social_order_extractions', 'social_ai_extraction_profile_id')) {
            Schema::table('social_order_extractions', function (Blueprint $table): void {
                $table->unsignedBigInteger('social_ai_extraction_profile_id')->nullable()->after('provider');
                $table->foreign('social_ai_extraction_profile_id', 'soe_ai_profile_fk')
                    ->references('id')->on('social_ai_extraction_profiles')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('social_order_extractions', 'prompt_version')) {
            Schema::table('social_order_extractions', function (Blueprint $table): void {
                $table->string('prompt_version', 50)->nullable()->after('model');
            });
        }

        if (! Schema::hasColumn('social_order_extractions', 'instructions_hash')) {
            Schema::table('social_order_extractions', function (Blueprint $table): void {
                $table->char('instructions_hash', 64)->nullable()->after('prompt_version');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('social_order_extractions', 'social_ai_extraction_profile_id')) {
            Schema::table('social_order_extractions', function (Blueprint $table): void {
                $table->dropForeign('soe_ai_profile_fk');
                $table->dropColumn('social_ai_extraction_profile_id');
            });
        }

        foreach (['prompt_version', 'instructions_hash'] as $column) {
            if (Schema::hasColumn('social_order_extractions', $column)) {
                Schema::table('social_order_extractions', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }

        Schema::dropIfExists('social_ai_extraction_profiles');
    }
};
