<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('social_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 191)->unique();
            $table->string('direction', 20)->index();
            $table->string('type', 30);
            $table->longText('body')->nullable();
            $table->longText('attachments')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->timestamp('sent_at')->index();
            $table->timestamps();

            $table->index(['social_conversation_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_messages');
    }
};
