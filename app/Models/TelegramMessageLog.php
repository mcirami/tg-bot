<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TelegramMessageLog extends Model
{
    protected $fillable = [
        'user_id',
        'telegram_connection_id',
        'chat_id',
        'telegram_message_id',
        'direction',
        'message_text',
        'matched_keyword',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function telegramConnection(): BelongsTo
    {
        return $this->belongsTo(TelegramConnection::class);
    }

    public function isIncoming(): bool
    {
        return $this->direction === 'incoming';
    }

    public function isOutgoing(): bool
    {
        return $this->direction === 'outgoing';
    }
}
