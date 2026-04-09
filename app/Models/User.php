<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\TelegramTriggerReply;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public mixed $telegramAccount = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function telegramAccount()
    {
        return $this->hasOne(TelegramAccount::class);
    }

    public function telegramConnection(): HasOne
    {
        return $this->hasOne(TelegramConnection::class);
    }

    public function telegramAutomation(): HasOne
    {
        return $this->hasOne(TelegramAutomation::class);
    }

    public function telegramMessageLogs(): HasMany
    {
        return $this->hasMany(TelegramMessageLog::class);
    }

    public function telegramChatStates(): HasMany
    {
        return $this->hasMany(TelegramChatState::class);
    }

    public function telegramTriggerReplies(): HasMany
    {
        return $this->hasMany(TelegramTriggerReply::class);
    }
}
