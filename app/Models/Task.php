<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'user_id', 'chat_id', 'title', 'task', 'scheduled_at', 'datetime', 'status', 
        'is_recurring', 'recurrence_type', 'last_notified_at', 'telegram_message_id',
        'notified_before', 'notified_at', 'completed_at'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'datetime' => 'datetime',
        'is_recurring' => 'boolean',
        'notified_before' => 'boolean',
        'notified_at' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
