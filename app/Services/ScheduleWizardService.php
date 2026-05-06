<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class ScheduleWizardService
{
    protected $telegram;
    protected $taskService;

    public function __construct(TelegramService $telegram, TaskService $taskService)
    {
        $this->telegram = $telegram;
        $this->taskService = $taskService;
    }

    public function startWizard(User $user)
    {
        $user->update(['bot_state' => 'wizard_date', 'temp_data' => []]);
        $this->sendDatePicker($user);
    }

    public function handleCallback(User $user, $data)
    {
        $state = $user->bot_state;
        $temp = $user->temp_data ?? [];

        if (str_starts_with($data, 'date_')) {
            $temp['date'] = str_replace('date_', '', $data);
            $user->update(['bot_state' => 'wizard_time', 'temp_data' => $temp]);
            $this->sendTimePicker($user, $temp['date']);
        } elseif (str_starts_with($data, 'time_')) {
            $temp['time'] = str_replace('time_', '', $data);
            $user->update(['bot_state' => 'wizard_title', 'temp_data' => $temp]);
            $this->sendTitlePrompt($user, $temp['time']);
        } elseif ($data === 'ai_suggest') {
            $this->sendAISuggestions($user);
        } elseif (str_starts_with($data, 'aitask_')) {
            $title = base64_decode(str_replace('aitask_', '', $data));
            $this->finalizeTask($user, $title);
        }
    }

    public function handleText(User $user, $text)
    {
        if ($user->bot_state === 'wizard_title') {
            $this->finalizeTask($user, $text);
        }
    }

    protected function sendDatePicker(User $user)
    {
        $today = now()->format('Y-m-d');
        $tomorrow = now()->addDay()->format('Y-m-d');

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📅 Bugun', 'callback_data' => 'date_' . $today],
                    ['text' => '📅 Ertaga', 'callback_data' => 'date_' . $tomorrow]
                ]
            ]
        ];

        $this->telegram->sendMessage($user->chat_id, "🗓 Qaysi kunga reja qo'shamiz?", $keyboard);
    }

    protected function sendTimePicker(User $user, $date)
    {
        $times = ['08:00', '09:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'];
        $keyboard = ['inline_keyboard' => []];
        $row = [];

        foreach ($times as $index => $time) {
            $row[] = ['text' => $time, 'callback_data' => 'time_' . $time];
            if (count($row) === 3 || $index === count($times) - 1) {
                $keyboard['inline_keyboard'][] = $row;
                $row = [];
            }
        }

        $this->telegram->sendMessage($user->chat_id, "⏰ Vaqtni tanlang:", $keyboard);
    }

    protected function sendTitlePrompt(User $user, $time)
    {
        // Conflict check
        $conflict = $user->tasks()->where('status', 'pending')
                         ->whereDate('scheduled_at', $user->temp_data['date'])
                         ->whereTime('scheduled_at', $time . ':00')
                         ->exists();

        $msg = "📝 Vazifa nomini yozib yuboring.\nMasalan: <i>Kitob o'qish</i>\n\n";
        
        if ($conflict) {
            $msg .= "⚠️ <b>Diqqat:</b> Bu vaqtda boshqa vazifangiz bor!";
        }

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🤖 AI bilan yaratish', 'callback_data' => 'ai_suggest']]
            ]
        ];

        $this->telegram->sendMessage($user->chat_id, $msg, $keyboard);
    }

    protected function sendAISuggestions(User $user)
    {
        $suggestions = ["📖 Kitob o'qish", "🏃‍♂️ Sport bilan shug'ullanish", "🕌 Namoz", "💻 Dasturlashni o'rganish"];
        $keyboard = ['inline_keyboard' => []];

        foreach ($suggestions as $s) {
            $keyboard['inline_keyboard'][] = [['text' => $s, 'callback_data' => 'aitask_' . base64_encode($s)]];
        }

        $this->telegram->sendMessage($user->chat_id, "🤖 AI tavsiyalari (tanlang):", $keyboard);
    }

    protected function finalizeTask(User $user, $title)
    {
        $date = $user->temp_data['date'];
        $time = $user->temp_data['time'];
        
        $scheduledAt = Carbon::parse("{$date} {$time}");

        $user->tasks()->create([
            'title' => $title,
            'scheduled_at' => $scheduledAt,
            'status' => 'pending'
        ]);

        $user->update(['bot_state' => null, 'temp_data' => null]);

        $this->telegram->sendMessage($user->chat_id, "✅ Zo'r! Vazifa muvaffaqiyatli saqlandi: <b>{$title}</b>");
    }
}
