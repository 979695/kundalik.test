<?php

namespace App\Services;

use App\Models\User;
use App\Models\BotLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BotService
{
    protected $telegram;
    protected $taskService;
    protected $habitService;

    public function __construct(
        TelegramService $telegram,
        TaskService $taskService,
        HabitService $habitService
    ) {
        $this->telegram = $telegram;
        $this->taskService = $taskService;
        $this->habitService = $habitService;
    }

    public function handleUpdate($update)
    {
        if (!isset($update['message'])) return;

        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $telegramId = $message['from']['id'];
        $text = $message['text'] ?? '';
        $firstName = $message['from']['first_name'] ?? 'Do\'stim';

        // Foydalanuvchini topish yoki yaratish
        $user = User::firstOrCreate(
            ['telegram_id' => $telegramId],
            [
                'name' => $firstName,
                'email' => $telegramId . '@telegram.com',
                'password' => bcrypt(Str::random(16)),
                'chat_id' => $chatId,
                'last_active_at' => now()
            ]
        );

        $user->update(['last_active_at' => now(), 'chat_id' => $chatId]);

        // Log saqlash
        BotLog::create([
            'user_id' => $user->id,
            'action' => 'message_received',
            'details' => $text
        ]);

        // Buyruqlarni qayta ishlash
        switch ($text) {
            case '/start':
                $this->handleStart($user);
                break;
            case '📋 Vazifalar ro\'yxati':
            case '/list':
                $this->handleListTasks($user);
                break;
            case '➕ Vazifa qo\'shish':
                $this->telegram->sendMessage($chatId, "📌 Yangi vazifa qo'shish uchun quyidagi formatda yozing:\n\n<code>/add 14:00 | Vazifa nomi</code>");
                break;
            case '📈 Odatlarim':
            case '/habits':
                $this->handleListHabits($user);
                break;
            case '🤖 Smart Reja (AI)':
                $this->handleSmartSchedule($user);
                break;
            default:
                if (str_starts_with($text, '/add')) {
                    $this->handleAddTask($user, str_replace('/add', '', $text));
                } else {
                    $this->telegram->sendMessage($chatId, "👋 Salom! Menyu tugmalaridan foydalanib o'z rejalaringizni boshqarishingiz mumkin.", $this->getMainMenu());
                }
                break;
        }
    }

    protected function handleStart(User $user)
    {
        $msg = "🌟 <b>Smart Life Assistant</b> botiga xush kelibsiz!\n\n" .
               "Men sizga vaqtingizni unumli sarflashda yordam beraman.\n\n" .
               "👇 Boshlash uchun menyudan foydalaning:";
        
        $this->telegram->sendMessage($user->chat_id, $msg, $this->getMainMenu());
    }

    protected function handleAddTask(User $user, $text)
    {
        $task = $this->taskService->addTask($user, $text);
        if ($task) {
            $msg = "✅ <b>Vazifa saqlandi!</b>\n\n" .
                   "📝 <b>Nomi:</b> {$task->title}\n" .
                   "⏰ <b>Vaqti:</b> {$task->scheduled_at->format('H:i')}\n\n" .
                   "Sizni o'z vaqtida ogohlantiraman.";
            $this->telegram->sendMessage($user->chat_id, $msg, $this->getMainMenu());
        } else {
            $this->telegram->sendMessage($user->chat_id, "❌ <b>Xato!</b>\n\nFormat: <code>/add 14:30 | Kitob o'qish</code>", $this->getMainMenu());
        }
    }

    protected function handleListTasks(User $user)
    {
        $tasks = $this->taskService->listTasks($user);
        if ($tasks->isEmpty()) {
            $this->telegram->sendMessage($user->chat_id, "📭 Hozircha rejalashtirilgan vazifalar yo'q.", $this->getMainMenu());
            return;
        }

        $msg = "📋 <b>Bugungi vazifalaringiz:</b>\n\n";
        foreach ($tasks as $task) {
            $msg .= "⏰ <b>{$task->scheduled_at->format('H:i')}</b> — {$task->title}\n";
        }

        $this->telegram->sendMessage($user->chat_id, $msg, $this->getMainMenu());
    }

    protected function handleSmartSchedule(User $user)
    {
        $this->taskService->generateSmartSchedule($user);
        $msg = "🤖 <b>AI:</b> Siz uchun bugungi reja tuzildi. Uni vazifalar ro'yxatida ko'rishingiz mumkin.";
        $this->telegram->sendMessage($user->chat_id, $msg, $this->getMainMenu());
    }

    protected function handleListHabits(User $user)
    {
        $habits = $this->habitService->listHabits($user);
        if ($habits->isEmpty()) {
            $this->telegram->sendMessage($user->chat_id, "📊 Odatlar ro'yxati hali bo'sh.", $this->getMainMenu());
            return;
        }

        $msg = "📈 <b>Sizning odatlaringiz:</b>\n\n";
        foreach ($habits as $habit) {
            $status = $habit->streak > 0 ? "🔥" : "🌑";
            $msg .= "{$status} <b>{$habit->title}</b>: {$habit->streak} kun\n";
        }
        $this->telegram->sendMessage($user->chat_id, $msg, $this->getMainMenu());
    }

    protected function getMainMenu()
    {
        return [
            'keyboard' => [
                [['text' => '📋 Vazifalar ro\'yxati'], ['text' => '➕ Vazifa qo\'shish']],
                [['text' => '📈 Odatlarim'], ['text' => '🤖 Smart Reja (AI)']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
    }
}
