<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

class NotificationService
{
    protected $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    public function checkAndNotify()
    {
        $now = Carbon::now();

        // 1. Taskdan 10 daqiqa oldin eslatma
        $upcomingTasks = Task::where('status', 'pending')
            ->where('notified_before', false)
            ->whereBetween('scheduled_at', [$now->copy()->addMinutes(9), $now->copy()->addMinutes(11)])
            ->get();

        foreach ($upcomingTasks as $task) {
            $msg = "⏳ <b>Tayyorlaning!</b>\n10 daqiqadan so'ng quyidagi vazifa boshlanadi:\n👉 {$task->title}";
            $this->telegram->sendMessage($task->user->chat_id, $msg);
            $task->update(['notified_before' => true]);
        }

        // 2. Task vaqti kelganda eslatma
        $dueTasks = Task::where('status', 'pending')
            ->where('notified_at', false)
            ->where('scheduled_at', '<=', $now)
            ->get();

        foreach ($dueTasks as $task) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Bajardim', 'callback_data' => 'done_' . $task->id],
                        ['text' => '❌ Bajarolmadim', 'callback_data' => 'fail_' . $task->id]
                    ]
                ]
            ];
            $msg = "🔔 <b>VAQT BO'LDI!</b>\n👉 {$task->title}\n\nVazifani bajarib, pastdagi tugmani bosing!";
            $this->telegram->sendMessage($task->user->chat_id, $msg, $keyboard);
            $task->update(['notified_at' => true]);
        }

        // 3. Inactivity (dangasalik) tekshiruvi - 24 soat kirmaganlarga
        $inactiveUsers = User::where('last_active_at', '<=', $now->copy()->subHours(24))
                             ->where('laziness_score', '<', 100)
                             ->get();
                             
        foreach ($inactiveUsers as $user) {
            $msg = "😴 Qayerlarda yuribsiz? Rivojlanish to'xtab qoldiku!\nDarhol bitta vazifa qo'shib, o'zingizni qo'lga oling!";
            $this->telegram->sendMessage($user->chat_id, $msg);
            // Kunda 1 marta jo'natish uchun vaqtni yangilaymiz (kichik xiyla)
            $user->update(['last_active_at' => $now->copy()->subHours(23)]);
        }
    }
}
