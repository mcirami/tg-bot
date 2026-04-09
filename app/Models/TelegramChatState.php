<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TelegramChatState extends Model
{
    protected $fillable = [
        'user_id',
        'chat_id',
        'last_incoming_message_at',
        'last_replied_at',
        'incoming_message_count',
        'reply_count_today',
        'reply_count_date',
        'last_message_text',
        'last_outgoing_message_at',
        'last_incoming_after_reply_at',
        'idle_follow_up_sent_at',
    ];

    protected $casts = [
        'last_incoming_message_at' => 'datetime',
        'last_replied_at' => 'datetime',
        'incoming_message_count' => 'integer',
        'reply_count_date' => 'date',
        'last_outgoing_message_at' => 'datetime',
        'last_incoming_after_reply_at' => 'datetime',
        'idle_follow_up_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resetDailyReplyCountIfNeeded(): void
    {
        $today = now()->toDateString();

        if (! $this->reply_count_date || $this->reply_count_date->toDateString() !== $today) {
            $this->reply_count_today = 0;
            $this->reply_count_date = $today;
        }
    }

    public function incrementReplyCount(): void
    {
        $this->resetDailyReplyCountIfNeeded();
        $this->reply_count_today++;
    }
}
