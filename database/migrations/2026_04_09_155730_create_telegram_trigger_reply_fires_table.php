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
        Schema::create('telegram_trigger_reply_fires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chat_id');
            $table->foreignId('trigger_reply_id')
                  ->constrained('telegram_trigger_replies')
                  ->cascadeOnDelete();
            $table->timestamp('fired_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'chat_id', 'trigger_reply_id'], 'telegram_trigger_reply_fires_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_trigger_reply_fires');
    }
};
