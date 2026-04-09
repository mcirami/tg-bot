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
        Schema::table('telegram_chat_states', function (Blueprint $table) {
            $table->unsignedInteger('incoming_message_count')
                  ->default(0)
                  ->after('last_replied_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_chat_states', function (Blueprint $table) {
            $table->dropColumn('incoming_message_count');
        });
    }
};
