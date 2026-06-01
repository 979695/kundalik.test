<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
        Log::info('[NotificationService] checkAndNotify boshlandi. Hozirgi vaqt: ' . $now->toDateTimeString() . ' (timezone: ' . $now->timezoneName . ')');

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

        // 2. Task vaqti kelganda/o'tganda eslatma (oddiy va budilnik rejimi)
        $dueTasks = Task::with('user')
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', $now)
            ->get();

        Log::info('[NotificationService] Muddati o\'tgan topshiriqlar soni: ' . $dueTasks->count() . ' ta. Hozirgi vaqt: ' . $now->toDateTimeString());

        foreach ($dueTasks as $task) {
            $user = $task->user;
            if (!$user || !$user->chat_id) continue;

            Log::info('[NotificationService] Topshiriq tekshirilmoqda. ID=' . $task->id . ' | "' . $task->title . '" | scheduled_at=' . $task->scheduled_at . ' | notified_at=' . ($task->notified_at ? 'true' : 'false') . ' | last_notified_at=' . $task->last_notified_at);

            $keyboard = [
                'inline_keyboard' => [[
                    ['text' => '✅ Bajardim',      'callback_data' => 'done_' . $task->id],
                    ['text' => '❌ Bajarolmadim', 'callback_data' => 'fail_' . $task->id],
                ]]
            ];

            // Agar foydalanuvchida budilnik rejimi yoqilgan bo'lsa (default true, null bo'lsa ham true)
            $isAlarmMode = filter_var($user->alarm_mode ?? true, FILTER_VALIDATE_BOOLEAN);

            if ($isAlarmMode) {
                // Eslatma hali yuborilmagan bo'lsa yoki oxirgi eslatmadan 5 daqiqa o'tgan bo'lsa
                $shouldSend = !$task->notified_at || 
                              (!$task->last_notified_at || Carbon::parse($task->last_notified_at)->diffInMinutes($now) >= 5);

                if ($shouldSend) {
                    // Eskisini o'chiramiz (agar mavjud bo'lsa va o'chirib bo'lsa)
                    if ($task->telegram_message_id) {
                        try {
                            $this->telegram->unpinChatMessage($user->chat_id, $task->telegram_message_id);
                            $this->telegram->deleteMessage($user->chat_id, $task->telegram_message_id);
                        } catch (\Exception $e) {
                            // ignore errors
                        }
                    }

                    // Oddiy matnli xabar — budilnik kabi takrorlanuvchi va tepaga qotiriladigan
                    $msg = "🔔⏰🔔⏰🔔⏰🔔⏰🔔⏰\n"
                         . "<b>VAZIFA VAQTI KELDI!</b>\n\n"
                         . "👉 <b>{$task->title}</b>\n\n"
                         . "⚠️ <i>Siz \"Bajardim\" yoki \"Bajarolmadim\" bosmaguningizcha har 5 daqiqada eslatib boraman!</i>\n"
                         . "🔔⏰🔔⏰🔔⏰🔔⏰🔔⏰";

                    Log::info('[NotificationService] sendMessage yuborilmoqda. Task ID=' . $task->id . ' | chat_id=' . $user->chat_id);
                    $response = $this->telegram->sendMessage($user->chat_id, $msg, $keyboard);

                    if ($response) {
                        $body = json_decode($response->getBody()->getContents(), true);
                        $messageId = $body['result']['message_id'] ?? null;
                        Log::info('[NotificationService] sendMessage natija. ok=' . ($body['ok'] ?? 'null') . ' | message_id=' . ($messageId ?? 'null'));
                        if ($messageId) {
                            $task->update([
                                'telegram_message_id' => $messageId,
                                'notified_at' => true,
                                'last_notified_at' => now(),
                            ]);
                            // Chat tepasiga qotiramiz (pin)
                            $this->telegram->pinChatMessage($user->chat_id, $messageId);
                        }
                    } else {
                        Log::error('[NotificationService] sendMessage MUVAFFAQIYATSIZ. Task ID=' . $task->id);
                    }
                }
            } else {
                // Oddiy rejim: faqat bir marta yuboriladi
                if (!$task->notified_at) {
                    $msg = "🔔 <b>VAQT BO'LDI!</b>\n\n👉 <b>{$task->title}</b>\n\nVazifani bajarib, pastdagi tugmani bosing!";
                    $response = $this->telegram->sendMessage($user->chat_id, $msg, $keyboard);
                    if ($response) {
                        $task->update([
                            'notified_at' => true,
                            'last_notified_at' => now(),
                        ]);
                    }
                }
            }
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

            // Namoz vaqti kelganda eslatma (oddiy matnli xabar)
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
