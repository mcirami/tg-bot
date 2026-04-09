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
        Schema::table('telegram_automations', function (Blueprint $table) {
            $table->unsignedInteger('idle_follow_up_minutes')
                  ->nullable()
                  ->after('typing_delay_max_seconds');

            $table->text('idle_follow_up_message')
                  ->nullable()
                  ->after('idle_follow_up_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_automations', function (Blueprint $table) {
            $table->dropColumn([
                'idle_follow_up_minutes',
                'idle_follow_up_message',
            ]);
        });
    }
};
