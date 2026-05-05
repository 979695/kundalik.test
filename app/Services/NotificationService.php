<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Barcha foydalanuvchilar uchun bildirishnomalarni tekshirish
     */
    public function checkAndNotify()
    {
        $now = Carbon::now();
        
        // 5 daqiqa ichida bajarilishi kerak bo'lgan tasklarni topish
        $tasks = Task::where('status', 'pending')
            ->where('scheduled_at', '<=', $now->copy()->addMinutes(5))
            ->where(function($query) use ($now) {
                $query->whereNull('last_notified_at')
                      ->orWhere('last_notified_at', '<', $now->copy()->subMinutes(10));
            })
            ->get();

        foreach ($tasks as $task) {
            $user = $task->user;
            if ($user && $user->chat_id) {
                $diff = $task->scheduled_at->diffInMinutes($now);
                $msg = "⏰ <b>Eslatma!</b>\n\nTask: {$task->title}\nVaqti: {$task->scheduled_at->format('H:i')}";
                
                if ($task->scheduled_at->isPast()) {
                    $msg .= "\n\n⚠️ <i>Vaqti o'tib ketdi! Iltimos, bajaring yoki qayta rejalashtiring.</i>";
                }

                $this->telegram->sendMessage($user->chat_id, $msg);
                
                $task->update(['last_notified_at' => $now]);
            }
        }

        // Faol bo'lmagan foydalanuvchilarni tekshirish (24 soat)
        $inactiveUsers = User::where('last_active_at', '<', $now->copy()->subDay())
            ->whereNotNull('chat_id')
            ->get();

        foreach ($inactiveUsers as $user) {
            $this->telegram->sendMessage($user->chat_id, "👋 Salom! Kuningiz qanday o'tyapti? Rejalaringizni yangilashni unutmang! ✨");
            $user->update(['last_active_at' => $now]); // Qayta-qayta bezovta qilmaslik uchun
        }
    }
}
