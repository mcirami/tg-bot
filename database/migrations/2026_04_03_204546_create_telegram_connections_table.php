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
        Schema::create('telegram_connections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('phone_number')->nullable();
            $table->string('session_name')->nullable();

            $table->string('telegram_user_id')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_first_name')->nullable();
            $table->string('telegram_last_name')->nullable();

            $table->string('phone_code_hash')->nullable();

            $table->enum('status', [
                'pending',
                'code_sent',
                'password_required',
                'connected',
                'failed',
            ])->default('pending');

            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error')->nullable();

            $table->unique('user_id');
            $table->index('phone_number');
            $table->index('status');
            $table->index('telegram_user_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_connections');
    }
};
