<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class TelegramConnection extends Model
{
    protected $fillable = [
        'user_id',
        'phone_number',
        'session_name',
        'telegram_user_id',
        'telegram_username',
        'telegram_first_name',
        'telegram_last_name',
        'phone_code_hash',
        'status',
        'connected_at',
        'last_error_at',
        'last_error',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function needsPassword(): bool
    {
        return $this->status === 'password_required';
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'last_error' => $message,
            'last_error_at' => now(),
        ]);
    }

    public function markConnected(array $telegramData = []): void
    {
        $this->update([
            'status' => 'connected',
            'connected_at' => now(),
            'last_error' => null,
            'last_error_at' => null,
            'telegram_user_id' => $telegramData['telegram_user_id'] ?? $this->telegram_user_id,
            'telegram_username' => $telegramData['telegram_username'] ?? $this->telegram_username,
            'telegram_first_name' => $telegramData['telegram_first_name'] ?? $this->telegram_first_name,
            'telegram_last_name' => $telegramData['telegram_last_name'] ?? $this->telegram_last_name,
        ]);
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(TelegramMessageLog::class);
    }
}
