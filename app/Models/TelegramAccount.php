<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramAccount extends Model
{
    protected $fillable = [
        'user_id',
        'phone_number',
        'telegram_user_id',
        'telegram_username',
        'session_path',
        'is_connected',
        'is_enabled',
        'prompt',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_connected' => 'boolean',
        'is_enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
