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
            $table->boolean('use_ai_replies')->default(false)->after('reply_text');
            $table->text('ai_system_prompt')->nullable()->after('use_ai_replies');
            $table->unsignedInteger('ai_max_input_chars')->default(1000)->after('ai_system_prompt');
            $table->unsignedInteger('ai_max_output_chars')->default(300)->after('ai_max_input_chars');
            $table->text('ai_instructions')->nullable()->after('is_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_automations', function (Blueprint $table) {
            $table->dropColumn([
                'use_ai_replies',
                'ai_system_prompt',
                'ai_max_input_chars',
                'ai_max_output_chars',
            ]);
        });
    }
};
