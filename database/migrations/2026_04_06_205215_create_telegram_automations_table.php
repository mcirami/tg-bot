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
        Schema::create('telegram_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->boolean('is_enabled')->default(false);

            $table->enum('reply_mode', [
                'always',
                'keyword',
            ])->default('always');

            $table->text('reply_text')->nullable();

            // Store keywords as JSON array, e.g. ["price", "info", "hello"]
            $table->json('keywords')->nullable();

            $table->unsignedInteger('daily_message_limit')->default(20);

            $table->unsignedInteger('per_chat_cooldown_minutes')->default(60);

            $table->unsignedInteger('mark_seen_delay_min_seconds')->default(5);
            $table->unsignedInteger('mark_seen_delay_max_seconds')->default(10);

            $table->unsignedInteger('typing_delay_min_seconds')->default(3);
            $table->unsignedInteger('typing_delay_max_seconds')->default(7);

            $table->timestamps();

            $table->unique('user_id');
            $table->index(['is_enabled', 'reply_mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_automations');
    }
};
