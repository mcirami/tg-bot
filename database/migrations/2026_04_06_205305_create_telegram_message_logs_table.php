<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('telegram_message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('telegram_connection_id')
                  ->constrained('telegram_connections')
                  ->cascadeOnDelete();

            $table->string('chat_id');
            $table->string('telegram_message_id')->nullable();

            $table->enum('direction', [
                'incoming',
                'outgoing',
            ]);

            $table->longText('message_text')->nullable();

            $table->string('matched_keyword')->nullable();

            // Examples: received, skipped_cooldown, skipped_limit, sent, failed
            $table->string('status')->nullable();

            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('telegram_connection_id');
            $table->index('chat_id');
            $table->index('telegram_message_id');
            $table->index('direction');
            $table->index('status');
            $table->index('sent_at');

            $table->index(['user_id', 'chat_id']);
            $table->index(['user_id', 'direction', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_message_logs');
    }
};
