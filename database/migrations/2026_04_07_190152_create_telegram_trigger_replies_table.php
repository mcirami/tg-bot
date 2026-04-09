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
        Schema::create('telegram_trigger_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->boolean('is_enabled')->default(true);
            $table->string('trigger_type'); // keyword | message_count
            $table->string('match_type')->nullable();

            $table->json('keywords')->nullable();
            $table->unsignedInteger('message_count')->nullable();
            $table->text('reply_text');

            $table->boolean('fire_once_per_chat')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_trigger_replies');
    }
};
