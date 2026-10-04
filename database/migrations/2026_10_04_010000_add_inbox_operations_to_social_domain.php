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
        Schema::table('social_conversations', function (Blueprint $table): void {
            $table->timestamp('last_inbound_at')->nullable()->after('last_message_at')->index();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->foreignId('assigned_by')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->after('assigned_by');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('social_conversation_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('social_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['social_conversation_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('social_reply_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title')->unique();
            $table->longText('body');
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('social_conversations')->select('id')->orderBy('id')->chunkById(200, function ($conversations): void {
            foreach ($conversations as $conversation) {
                $lastInboundAt = DB::table('social_messages')
                    ->where('social_conversation_id', $conversation->id)
                    ->where('direction', 'inbound')
                    ->max('sent_at');
                if ($lastInboundAt !== null) {
                    DB::table('social_conversations')
                        ->where('id', $conversation->id)
                        ->update(['last_inbound_at' => $lastInboundAt]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_reply_templates');
        Schema::dropIfExists('social_conversation_reads');

        Schema::table('social_conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn(['last_inbound_at', 'assigned_at', 'archived_at']);
        });
    }
};
