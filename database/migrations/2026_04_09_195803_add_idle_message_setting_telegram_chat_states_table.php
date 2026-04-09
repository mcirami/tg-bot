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
            $table->timestamp('last_outgoing_message_at')->nullable()->after('last_replied_at');
            $table->timestamp('last_incoming_after_reply_at')->nullable()->after('last_outgoing_message_at');
            $table->timestamp('idle_follow_up_sent_at')->nullable()->after('last_incoming_after_reply_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_chat_states', function (Blueprint $table) {
            $table->dropColumn([
                'last_outgoing_message_at',
                'last_incoming_after_reply_at',
                'idle_follow_up_sent_at',
            ]);
        });
    }
};
