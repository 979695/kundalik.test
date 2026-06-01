<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'telegram_id', 'chat_id', 'last_active_at', 'xp', 'level', 'streak', 'laziness_score', 'islamic_reminders', 'alarm_mode', 'bot_state', 'temp_data'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function habits()
    {
        return $this->hasMany(Habit::class);
    }

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
            'temp_data' => 'array',
            'alarm_mode' => 'boolean',
        ];
    }
}
