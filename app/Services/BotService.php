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

    public function __construct(
        TelegramService $telegram,
        TaskService $taskService,
        HabitService $habitService,
        GamificationService $gamificationService,
        IslamicService $islamicService
    ) {
        $this->telegram = $telegram;
        $this->taskService = $taskService;
        $this->habitService = $habitService;
        $this->gamificationService = $gamificationService;
        $this->islamicService = $islamicService;
    }

    public function handleUpdate($update)
    {
        if (isset($update['callback_query'])) {
            return $this->handleCallback($update['callback_query']);
        }

        if (!isset($update['message'])) return;

        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $telegramId = $message['from']['id'];
        $text = $message['text'] ?? '';
        $firstName = $message['from']['first_name'] ?? 'Do\'stim';

        $user = User::firstOrCreate(
            ['telegram_id' => $telegramId],
            [
                'name' => $firstName,
                'email' => $telegramId . '@telegram.com',
                'password' => bcrypt(Str::random(16)),
                'chat_id' => $chatId,
                'last_active_at' => now(),
                'xp' => 0,
                'level' => 1
            ]
        );

        $user->update(['last_active_at' => now(), 'chat_id' => $chatId]);

        BotLog::create(['user_id' => $user->id, 'action' => 'message', 'details' => $text]);

        switch ($text) {
            case '/start':
                $this->handleStart($user);
                break;
            case '📅 Rejalar':
            case '/list':
                $this->handleListTasks($user);
                break;
            case '➕ Qo\'shish':
                $this->telegram->sendMessage($chatId, "📌 Yangi vazifa qo'shish:\n\n<code>/add 14:00 | Kitob o'qish</code>");
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
            default:
                if (str_starts_with($text, '/add')) {
                    $this->handleAddTask($user, str_replace('/add', '', $text));
                } else {
                    $this->telegram->sendMessage($chatId, "👋 Menyu orqali tanlang:", $this->getMainMenu());
                }
                break;
        }
    }

    protected function handleCallback($callback)
    {
        $data = $callback['data'];
        $chatId = $callback['message']['chat']['id'];
        $user = User::where('telegram_id', $callback['from']['id'])->first();

        if (!$user) return;

        if (str_starts_with($data, 'done_')) {
            $taskId = str_replace('done_', '', $data);
            $task = $user->tasks()->find($taskId);
            if ($task && $task->status !== 'completed') {
                $task->update(['status' => 'completed', 'completed_at' => now()]);
                
                $reward = $this->gamificationService->rewardTaskCompletion($user);
                $msg = "🎉 Barakalla! Vazifa bajarildi.\n⭐ +10 XP oldingiz!";
                
                if ($reward['level_up']) {
                    $msg .= "\n\n🚀 TABRIKLAYMIZ! Siz yangi darajaga ko'tarildingiz: Level {$reward['new_level']}!";
                }
                
                $this->telegram->sendMessage($chatId, $msg);
            }
        } elseif (str_starts_with($data, 'fail_')) {
            $taskId = str_replace('fail_', '', $data);
            $task = $user->tasks()->find($taskId);
            if ($task && $task->status !== 'completed') {
                $task->update(['status' => 'failed']);
                
                $punishment = $this->gamificationService->applyPunishment($user);
                $msg = "❌ Vazifa bajarilmadi!\n\nJAZO G'ILDIRAGI aylandi 🎰\nSizning jazongiz:\n👉 <b>{$punishment}</b>";
                
                $this->telegram->sendMessage($chatId, $msg);
            }
        }
    }

    protected function handleStart(User $user)
    {
        $msg = "🌟 <b>Smart Life System</b>\n\nMen sizni rivojlantirish uchun yaratilganman. Har bir qadamda sizni nazorat qilaman va rag'batlantiraman!";
        $this->telegram->sendMessage($user->chat_id, $msg, $this->getMainMenu());
    }

    protected function handleAddTask(User $user, $text)
    {
        $task = $this->taskService->addTask($user, $text);
        if ($task) {
            $msg = "✅ Vazifa saqlandi: <b>{$task->title}</b> ({$task->scheduled_at->format('H:i')})";
            $this->telegram->sendMessage($user->chat_id, $msg, $this->getMainMenu());
        } else {
            $this->telegram->sendMessage($user->chat_id, "❌ Xato format: <code>/add 14:30 | Vazifa</code>", $this->getMainMenu());
        }
    }

    protected function handleListTasks(User $user)
    {
        $tasks = $this->taskService->listTasks($user);
        if ($tasks->isEmpty()) {
            $this->telegram->sendMessage($user->chat_id, "📭 Bugun uchun vazifalar yo'q.");
            return;
        }

        foreach ($tasks as $task) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Bajardim', 'callback_data' => 'done_' . $task->id],
                        ['text' => '❌ Bajarolmadim', 'callback_data' => 'fail_' . $task->id]
                    ]
                ]
            ];
            $msg = "⏰ <b>{$task->scheduled_at->format('H:i')}</b> — {$task->title}";
            $this->telegram->sendMessage($user->chat_id, $msg, $keyboard);
        }
    }

    protected function handleStatistics(User $user)
    {
        $completed = $user->tasks()->where('status', 'completed')->whereDate('created_at', today())->count();
        $msg = "📊 <b>Statistika:</b>\n\n✅ Bugun bajarildi: {$completed}\n🔥 Streak: {$user->streak} kun\n😴 Dangasalik: {$user->laziness_score}%";
        $this->telegram->sendMessage($user->chat_id, $msg);
    }

    protected function handleLevel(User $user)
    {
        $msg = "🎯 <b>Gamification:</b>\n\n⭐ Level: {$user->level}\n✨ XP: {$user->xp}/" . ($user->level * 100);
        $this->telegram->sendMessage($user->chat_id, $msg);
    }

    protected function handleIslamic(User $user)
    {
        $zikr = $this->islamicService->getDailyZikr();
        $msg = "🕌 <b>Kunlik Zikr:</b>\n\n📿 {$zikr}\n\n<i>Namoz vaqtlari avtomatik eslatiladi.</i>";
        $this->telegram->sendMessage($user->chat_id, $msg);
    }

    protected function getMainMenu()
    {
        return [
            'keyboard' => [
                [['text' => '📅 Rejalar'], ['text' => '➕ Qo\'shish']],
                [['text' => '📊 Statistika'], ['text' => '🎯 Level']],
                [['text' => '🕌 Namoz'], ['text' => '⚙️ Sozlamalar']],
            ],
            'resize_keyboard' => true,
        ];
    }
}
