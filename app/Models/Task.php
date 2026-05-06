<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'user_id', 'title', 'scheduled_at', 'status', 
        'is_recurring', 'recurrence_type', 'last_notified_at',
        'notified_before', 'notified_at', 'completed_at'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
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
