<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TelegramAutomation extends Model
{
    protected $fillable = [
        'user_id',
        'is_enabled',
        'ai_instructions',
        'daily_message_limit',
        'per_chat_cooldown_minutes',
        'mark_seen_delay_min_seconds',
        'mark_seen_delay_max_seconds',
        'typing_delay_min_seconds',
        'typing_delay_max_seconds',
        'idle_follow_up_minutes',
        'idle_follow_up_message',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'use_ai_replies' => 'boolean',
        'keywords' => 'array',
        'idle_follow_up_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAlwaysMode(): bool
    {
        return $this->reply_mode === 'always';
    }

    public function isKeywordMode(): bool
    {
        return $this->reply_mode === 'keyword';
    }

    public function keywordList(): array
    {
        return array_values(array_filter(
            $this->keywords ?? [],
            fn ($keyword) => is_string($keyword) && trim($keyword) !== ''
        ));
    }

}
