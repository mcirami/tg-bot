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
        Schema::create('telegram_chat_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('chat_id');

            $table->timestamp('last_incoming_message_at')->nullable();
            $table->timestamp('last_replied_at')->nullable();

            $table->unsignedInteger('reply_count_today')->default(0);

            $table->date('reply_count_date')->nullable();

            $table->text('last_message_text')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'chat_id']);

            $table->index('chat_id');
            $table->index('last_incoming_message_at');
            $table->index('last_replied_at');
            $table->index(['user_id', 'reply_count_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_chat_states');
    }
};
