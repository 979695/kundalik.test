<?php

namespace App\Services;

use App\Models\User;
use App\Models\BotLog;
use Illuminate\Support\Str;

class BotService
{
    protected $telegram;
    protected $taskService;
    protected $habitService;
    protected $gamificationService;
    protected $islamicService;
    protected $scheduleWizard;

    public function __construct(
        TelegramService $telegram,
        TaskService $taskService,
        HabitService $habitService,
        GamificationService $gamificationService,
        IslamicService $islamicService,
        ScheduleWizardService $scheduleWizard
    ) {
        $this->telegram = $telegram;
        $this->taskService = $taskService;
        $this->habitService = $habitService;
        $this->gamificationService = $gamificationService;
        $this->islamicService = $islamicService;
        $this->scheduleWizard = $scheduleWizard;
    }

    public function handleUpdate($update)
    {
        if (isset($update['callback_query'])) {
            return $this->handleCallback($update['callback_query']);
        }

        if (!isset($update['message'])) return;

        $message   = $update['message'];
        $chatId    = $message['chat']['id'];
        $telegramId = $message['from']['id'];
        $text      = $message['text'] ?? '';
        $firstName = $message['from']['first_name'] ?? "Do'stim";

        $user = User::firstOrCreate(
            ['telegram_id' => $telegramId],
            [
                'name'           => $firstName,
                'email'          => $telegramId . '@telegram.com',
                'password'       => bcrypt(Str::random(16)),
                'chat_id'        => $chatId,
                'last_active_at' => now(),
                'xp'             => 0,
                'level'          => 1,
            ]
        );

        $user->update(['last_active_at' => now(), 'chat_id' => $chatId]);

        BotLog::create(['user_id' => $user->id, 'action' => 'message', 'details' => $text]);

        // Wizard holati
        if ($user->bot_state && str_starts_with($user->bot_state, 'wizard_')) {
            return $this->scheduleWizard->handleText($user, $text);
        }

        // Qo'lda yozish holati
        if ($user->bot_state === 'manual_entry') {
            return $this->handleManualEntry($user, $text);
        }

        switch ($text) {
            case '/start':
                $this->handleStart($user);
                break;
            case '📅 Rejalar':
            case '/list':
                $this->handleListTasks($user);
                break;
            case '➕ Qo\'shish':
            case '/add':
                $this->scheduleWizard->startWizard($user);
                break;
            case "⌨️ Qo'lda yozish":
                $user->update(['bot_state' => 'manual_entry', 'temp_data' => null]);
                $this->telegram->sendMessage(
                    $user->chat_id,
                    "⌨️ Iltimos vazifani quyidagi formatlardan birida kiriting:\n<code>14:30 | Vazifa nomi</code>\nYoki to'liq sana bilan:\n<code>2026-05-06 18:10 | Vazifa nomi</code>"
                );
                break;
            case '🤖 AI yordam':
                $this->sendAIAssist($user);
                break;
            case '📊 Statistika':
                $this->handleStatistics($user);
                break;
            case '🎯 Level':
                $this->handleLevel($user);
                break;
            case '🕌 Namoz':
                $this->handleIslamic($user);
                break;
            case '⚙️ Sozlamalar':
                $this->handleSettings($user);
                break;
            default:
                $this->telegram->sendMessage($chatId, "👋 Menyu orqali tanlang:", $this->getMainMenu());
                break;
        }
    }

    protected function handleCallback($callback)
    {
        $data   = $callback['data'];
        $chatId = $callback['message']['chat']['id'];
        $user   = User::where('telegram_id', $callback['from']['id'])->first();

        if (!$user) return;

        if ($user->bot_state && str_starts_with($user->bot_state, 'wizard_')) {
            return $this->scheduleWizard->handleCallback($user, $data);
        }

        if ($data === 'add_task') {
            return $this->scheduleWizard->startWizard($user);
        }

        if ($data === 'manual_entry') {
            $user->update(['bot_state' => 'manual_entry', 'temp_data' => null]);
            return $this->telegram->sendMessage(
                $chatId,
                "⌨️ Iltimos vazifani quyidagi formatlardan birida kiriting:\n<code>14:30 | Vazifa nomi</code>\nYoki to'liq sana bilan:\n<code>2026-05-06 18:10 | Vazifa nomi</code>"
            );
        }

        if ($data === 'ai_suggest_start') {
            return $this->sendAIAssist($user);
        }

        if ($data === 'toggle_prayer_reminder') {
            $user->update(['islamic_reminders' => !$user->islamic_reminders]);
            $status = $user->islamic_reminders ? "yoqildi" : "o'chirildi";
            $this->telegram->sendMessage($chatId, "🕌 Namoz vaqtlari eslatmasi {$status}!");
            return $this->handleIslamic($user);
        }

        if ($data === 'settings_toggle_prayer') {
            $user->update(['islamic_reminders' => !$user->islamic_reminders]);
            $messageId = $callback['message']['message_id'] ?? null;
            return $this->handleSettings($user, $messageId);
        }

        if ($data === 'settings_toggle_alarm') {
            $user->update(['alarm_mode' => !$user->alarm_mode]);
            $messageId = $callback['message']['message_id'] ?? null;
            return $this->handleSettings($user, $messageId);
        }

        if (str_starts_with($data, 'aitask_')) {
            $title = base64_decode(str_replace('aitask_', '', $data));
            return $this->scheduleWizard->startWizard($user, $title);
        }

        if (str_starts_with($data, 'done_')) {
            $taskId = str_replace('done_', '', $data);
            $task   = $user->tasks()->find($taskId);
            if ($task && $task->status !== 'completed') {
                $task->update(['status' => 'completed', 'completed_at' => now()]);

                // Budilnikni o'chirish va unpin qilish
                if ($task->telegram_message_id) {
                    try {
                        $this->telegram->unpinChatMessage($chatId, $task->telegram_message_id);
                        $this->telegram->deleteMessage($chatId, $task->telegram_message_id);
                    } catch (\Exception $e) {
                        // ignore errors
                    }
                }

                $reward = $this->gamificationService->rewardTaskCompletion($user);
                $msg    = "🎉 Barakalla! <b>\"{$task->title}\"</b> vazifasi muvaffaqiyatli bajarildi.\n⭐ +10 XP oldingiz!";

                if (!empty($reward['level_up'])) {
                    $msg .= "\n\n🚀 TABRIKLAYMIZ! Siz yangi darajaga ko'tarildingiz: Level {$reward['new_level']}!";
                }

                $this->telegram->sendMessage($chatId, $msg);
            }
        } elseif (str_starts_with($data, 'fail_')) {
            $taskId = str_replace('fail_', '', $data);
            $task   = $user->tasks()->find($taskId);
            if ($task && $task->status !== 'completed') {
                $task->update(['status' => 'failed']);

                // Budilnikni o'chirish va unpin qilish
                if ($task->telegram_message_id) {
                    try {
                        $this->telegram->unpinChatMessage($chatId, $task->telegram_message_id);
                        $this->telegram->deleteMessage($chatId, $task->telegram_message_id);
                    } catch (\Exception $e) {
                        // ignore errors
                    }
                }

                $punishment = $this->gamificationService->applyPunishment($user);
                $msg        = "❌ <b>\"{$task->title}\"</b> vazifasi bajarilmadi!\n\nJAZO G'ILDIRAGI aylandi 🎰\nSizning jazongiz:\n👉 <b>{$punishment}</b>";

                $this->telegram->sendMessage($chatId, $msg);
            }
        }
    }

    protected function handleStart(User $user)
    {
        $user->update(['bot_state' => null, 'temp_data' => null]);
        $msg = "🌟 <b>Smart Life System</b>\n\nMen sizni rivojlantirish uchun yaratilganman. Har bir qadamda sizni nazorat qilaman va rag'batlantiraman!";
        $this->telegram->sendMessage($user->chat_id, $msg, $this->getMainMenu());
    }

    protected function handleManualEntry(User $user, $text)
    {
        if (!Str::contains($text, '|')) {
            $this->telegram->sendMessage(
                $user->chat_id,
                "⚠️ Iltimos vazifani quyidagi formatda yuboring:\n<code>14:30 | Vazifa nomi</code>\nYoki:\n<code>2026-05-06 18:10 | Vazifa nomi</code>"
            );
            return;
        }

        $task = $this->taskService->addTask($user, $text);
        if ($task) {
            $this->telegram->sendMessage(
                $user->chat_id,
                "✅ Vazifa saqlandi:\n<b>{$task->title}</b>\n⏰ {$task->scheduled_at->format('Y-m-d H:i')}",
                $this->getMainMenu()
            );
            $user->update(['bot_state' => null, 'temp_data' => null]);
            return;
        }

        $this->telegram->sendMessage(
            $user->chat_id,
            "⚠️ Noto'g'ri format yoki vaqt kelib bo'lgan. Iltimos, quyidagi formatlardan birida yuboring:\n<code>14:30 | Vazifa nomi</code>\n<code>2026-05-06 18:10 | Vazifa nomi</code>"
        );
    }

    protected function sendAIAssist(User $user)
    {
        $suggestions = [
            "Kitob o'qish",
            "Sport bilan shug'ullanish",
            "Namozga tayyorlanish",
            "Dasturlash bo'yicha mashq",
        ];

        $keyboard = ['inline_keyboard' => []];
        foreach ($suggestions as $suggestion) {
            $keyboard['inline_keyboard'][] = [
                [['text' => $suggestion, 'callback_data' => 'aitask_' . base64_encode($suggestion)]]
            ];
        }
        $keyboard['inline_keyboard'][] = [
            ['text' => '➕ Reja qo\'shish',    'callback_data' => 'add_task'],
            ['text' => '⌨️ Qo\'lda yozish', 'callback_data' => 'manual_entry'],
        ];

        $this->telegram->sendMessage(
            $user->chat_id,
            "🤖 AI yordam. Quyidagi takliflar sizga yordam beradi. Tanlang yoki o'zingiz yozing.",
            $keyboard
        );
    }

    protected function handleListTasks(User $user)
    {
        $tasks = $this->taskService->listTasks($user);
        if ($tasks->isEmpty()) {
            $this->telegram->sendMessage($user->chat_id, "📭 Bugun uchun vazifalar yo'q.\n\n➕ Yangi vazifa qo'shish uchun tugmani bosing.", [
                'inline_keyboard' => [[
                    ['text' => '➕ Vazifa qo\'shish', 'callback_data' => 'add_task']
                ]]
            ]);
            return;
        }

        $msg = "📋 <b>Sizning rejalaringiz:</b>\n\n";
        $keyboard = ['inline_keyboard' => []];

        foreach ($tasks as $task) {
            $statusLabel = match ($task->status) {
                'completed' => '✅ Bajarildi',
                'failed'    => '❌ Bajarilmadi',
                default     => '⏳ Kutmoqda',
            };

            $msg .= "⏰ <b>{$task->scheduled_at->format('H:i')}</b> — {$task->title}\n<em>{$statusLabel}</em>\n\n";

            if ($task->status === 'pending') {
                $timeStr = $task->scheduled_at->format('H:i');
                $keyboard['inline_keyboard'][] = [
                    ['text' => "✅ {$timeStr} — Bajardim", 'callback_data' => 'done_' . $task->id],
                    ['text' => "❌ Bajarolmadim", 'callback_data' => 'fail_' . $task->id],
                ];
            }
        }

        $this->telegram->sendMessage($user->chat_id, $msg, $keyboard);
    }

    protected function handleStatistics(User $user)
    {
        $completed = $user->tasks()->where('status', 'completed')->whereDate('created_at', today())->count();
        $failed    = $user->tasks()->where('status', 'failed')->whereDate('created_at', today())->count();
        $pending   = $user->tasks()->where('status', 'pending')->whereDate('scheduled_at', today())->count();

        $msg = "📊 <b>Bugungi statistika:</b>\n\n"
             . "✅ Bajarildi: <b>{$completed}</b>\n"
             . "❌ Bajarilmadi: <b>{$failed}</b>\n"
             . "⏳ Kutmoqda: <b>{$pending}</b>\n"
             . "🔥 Streak: <b>{$user->streak}</b> kun\n"
             . "😴 Dangasalik: <b>{$user->laziness_score}%</b>";
        $this->telegram->sendMessage($user->chat_id, $msg);
    }

    protected function handleLevel(User $user)
    {
        $nextXp  = $user->level * 100;
        $percent = $nextXp > 0 ? round(($user->xp / $nextXp) * 100) : 0;
        $bar     = str_repeat('▓', intval($percent / 10)) . str_repeat('░', 10 - intval($percent / 10));

        $msg = "🎯 <b>Gamification:</b>\n\n"
             . "⭐ Level: <b>{$user->level}</b>\n"
             . "✨ XP: <b>{$user->xp}</b> / {$nextXp}\n"
             . "📈 Progress: [{$bar}] {$percent}%";
        $this->telegram->sendMessage($user->chat_id, $msg);
    }

    protected function handleIslamic(User $user)
    {
        $zikr       = $this->islamicService->getDailyZikr();
        $prayerTimes = $this->islamicService->getPrayerTimes();

        $prayers = [
            'Fajr'    => '🌅 Bomdod',
            'Dhuhr'   => '☀️ Peshin',
            'Asr'     => '🌤 Asr',
            'Maghrib' => '🌇 Shom',
            'Isha'    => '🌙 Xufton',
        ];

        $prayerMsg = '';
        if ($prayerTimes) {
            $prayerMsg = "\n\n🕌 <b>Bugungi namoz vaqtlari (Toshkent):</b>\n";
            foreach ($prayers as $key => $name) {
                if (isset($prayerTimes[$key])) {
                    $prayerMsg .= "{$name}: <b>{$prayerTimes[$key]}</b>\n";
                }
            }
        }

        // Namoz eslatmasini yoqish tugmasi
        $reminderStatus = $user->islamic_reminders ? '🔕 Eslatmani o\'chirish' : '🔔 Eslatmani yoqish';
        $keyboard = [
            'inline_keyboard' => [[
                ['text' => $reminderStatus, 'callback_data' => 'toggle_prayer_reminder']
            ]]
        ];

        $msg = "🕌 <b>Kunlik Zikr:</b>\n\n📿 {$zikr}{$prayerMsg}";
        $this->telegram->sendMessage($user->chat_id, $msg, $keyboard);
    }

    protected function handleSettings(User $user, $messageId = null)
    {
        $prayerStatus = $user->islamic_reminders ? "🕌 Namoz: ✅ Yoqilgan" : "🕌 Namoz: ❌ O'chirilgan";
        $alarmStatus  = $user->alarm_mode ? "⏰ Budilnik: ✅ Yoqilgan" : "⏰ Budilnik: ❌ O'chirilgan";

        $keyboard = [
            'inline_keyboard' => [
                [[
                    'text' => $prayerStatus,
                    'callback_data' => 'settings_toggle_prayer'
                ]],
                [[
                    'text' => $alarmStatus,
                    'callback_data' => 'settings_toggle_alarm'
                ]]
            ]
        ];

        $msg = "⚙️ <b>Sozlamalar bo'limi</b>\n\nBu yerda eslatmalar va bildirishnoma rejimlarini sozlashingiz mumkin:\n\n"
             . "1. <b>Namoz vaqtlari:</b> Kunlik besh vaqt namoz eslatmalari.\n"
             . "2. <b>Budilnik rejimi:</b> Vazifa vaqti kelganda eshitiladigan va siz vazifani bajardim yoki bajarolmadim deb belgilamaguningizcha har 5 daqiqada takrorlanuvchi qat'iy ovozli xabar! U chat tepasiga ham qotirib qo'yiladi.";

        if ($messageId) {
            $this->telegram->editMessageText($user->chat_id, $messageId, $msg, $keyboard);
        } else {
            $this->telegram->sendMessage($user->chat_id, $msg, $keyboard);
        }
    }

    protected function getMainMenu()
    {
        return [
            'keyboard' => [
                [['text' => '📅 Rejalar'],        ['text' => '➕ Qo\'shish']],
                [['text' => "⌨️ Qo'lda yozish"], ['text' => '🤖 AI yordam']],
                [['text' => '📊 Statistika'],     ['text' => '🎯 Level']],
                [['text' => '🕌 Namoz'],         ['text' => '⚙️ Sozlamalar']],
            ],
            'resize_keyboard' => true,
        ];
    }
}
