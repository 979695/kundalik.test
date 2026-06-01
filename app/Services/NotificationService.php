<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

class NotificationService
{
    protected $telegram;
    protected $islamicService;

    public function __construct(TelegramService $telegram, IslamicService $islamicService)
    {
        $this->telegram      = $telegram;
        $this->islamicService = $islamicService;
    }

    public function checkAndNotify()
    {
        $now = Carbon::now();

        // 1. Taskdan 10 daqiqa oldin eslatma
        $upcomingTasks = Task::with('user')
            ->where('status', 'pending')
            ->where('notified_before', false)
            ->whereBetween('scheduled_at', [
                $now->copy()->addMinutes(9),
                $now->copy()->addMinutes(11),
            ])
            ->get();

        foreach ($upcomingTasks as $task) {
            if (!$task->user || !$task->user->chat_id) continue;

            $msg = "⏳ <b>Tayyorlaning!</b>\n10 daqiqadan so'ng quyidagi vazifa boshlanadi:\n👉 {$task->title}\n⏰ {$task->scheduled_at->format('H:i')}";
            $this->telegram->sendMessage($task->user->chat_id, $msg);
            $task->update(['notified_before' => true]);
        }

        // 2. Task vaqti kelganda eslatma
        $dueTasks = Task::with('user')
            ->where('status', 'pending')
            ->where('notified_at', false)
            ->where('scheduled_at', '<=', $now)
            ->get();

        foreach ($dueTasks as $task) {
            if (!$task->user || !$task->user->chat_id) continue;

            $keyboard = [
                'inline_keyboard' => [[
                    ['text' => '✅ Bajardim',      'callback_data' => 'done_' . $task->id],
                    ['text' => '❌ Bajarolmadim', 'callback_data' => 'fail_' . $task->id],
                ]]
            ];
            $msg = "🔔 <b>VAQT BO'LDI!</b>\n👉 {$task->title}\n\nVazifani bajarib, pastdagi tugmani bosing!";
            $this->telegram->sendMessage($task->user->chat_id, $msg, $keyboard);
            $task->update(['notified_at' => true]);
        }

        // 3. Namoz vaqtlari eslatmasi
        $this->checkPrayerTimes($now);

        // 4. Inactivity tekshiruvi - 24 soat kirmaganlarga
        $inactiveUsers = User::where('last_active_at', '<=', $now->copy()->subHours(24))
            ->where('laziness_score', '<', 100)
            ->get();

        foreach ($inactiveUsers as $user) {
            if (!$user->chat_id) continue;

            $msg = "😴 Qayerlarda yuribsiz? Rivojlanish to'xtab qoldiku!\nDarhol bitta vazifa qo'shib, o'zingizni qo'lga oling! 💪";
            $this->telegram->sendMessage($user->chat_id, $msg);
            // Qayta jo'natmaslik uchun (23 soat keyingisiga surish)
            $user->update(['last_active_at' => $now->copy()->subHours(23)]);
        }
    }

    /**
     * Namoz vaqtlarini tekshirib eslatma yuborish
     */
    protected function checkPrayerTimes(Carbon $now)
    {
        // Faqat islamic_reminders yoqilgan foydalanuvchilar
        $users = User::where('islamic_reminders', true)->get();
        if ($users->isEmpty()) return;

        $prayerTimes = $this->islamicService->getPrayerTimes();
        if (!$prayerTimes) return;

        $prayers = [
            'Fajr'    => ['emoji' => '🌅', 'name' => 'Bomdod'],
            'Dhuhr'   => ['emoji' => '☀️',  'name' => 'Peshin'],
            'Asr'     => ['emoji' => '🌤',  'name' => 'Asr'],
            'Maghrib' => ['emoji' => '🌇', 'name' => 'Shom'],
            'Isha'    => ['emoji' => '🌙', 'name' => 'Xufton'],
        ];

        $currentTime = $now->format('H:i');

        foreach ($prayers as $key => $info) {
            if (!isset($prayerTimes[$key])) continue;

            $prayerTimeStr  = substr($prayerTimes[$key], 0, 5); // "HH:MM" formatiga keltirish
            $prayerCarbon   = Carbon::createFromFormat('H:i', $prayerTimeStr);

            // 10 daqiqa oldin eslatma
            $reminderTime = $prayerCarbon->copy()->subMinutes(10)->format('H:i');

            if ($currentTime === $reminderTime) {
                foreach ($users as $user) {
                    if (!$user->chat_id) continue;
                    $msg = "{$info['emoji']} <b>{$info['name']} namozi</b> 10 daqiqadan keyin!\n"
                         . "⏰ Soat <b>{$prayerTimeStr}</b> da boshlanadi.\n\n"
                         . "Tahorat oling va tayyorlaning 🤲";
                    $this->telegram->sendMessage($user->chat_id, $msg);
                }
            }

            // Namoz vaqti kelganda eslatma
            if ($currentTime === $prayerTimeStr) {
                foreach ($users as $user) {
                    if (!$user->chat_id) continue;
                    $msg = "{$info['emoji']} <b>{$info['name']} namozi vaqti keldi!</b>\n\n"
                         . "Allohni zikr qiling va namoz o'qing 🤲\n\n"
                         . "<i>«Aslatu xayrun minan-nawm»</i>";
                    $this->telegram->sendMessage($user->chat_id, $msg);
                }
            }
        }
    }
}
