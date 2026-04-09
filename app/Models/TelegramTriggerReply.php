<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramTriggerReply extends Model
{
    protected $fillable = [
        'user_id',
        'is_enabled',
        'trigger_type',
        'match_type',
        'keywords',
        'message_count',
        'reply_text',
        'fire_once_per_chat',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'keywords' => 'array',
        'fire_once_per_chat' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function keywordList(): array
    {
        return array_values(array_filter(
            $this->keywords ?? [],
            fn ($keyword) => is_string($keyword) && trim($keyword) !== ''
        ));
    }
}
