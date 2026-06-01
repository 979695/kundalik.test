<?php

namespace App\Services;

use App\Models\User;
use App\Services\TelegramService;
use App\Services\TaskService;
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

    public function startWizard(User $user, $presetTask = null)
    {
        $tempData = [];
        if ($presetTask) {
            $tempData['task'] = $presetTask;
        }

        $user->update(['bot_state' => 'wizard_date', 'temp_data' => $tempData]);
        $this->sendDatePicker($user);
    }

    public function handleCallback(User $user, $data)
    {
        $temp = $user->temp_data ?? [];

        if (str_starts_with($data, 'calendar_prev_') || str_starts_with($data, 'calendar_next_')) {
            [$action, $year, $month] = explode('_', $data);
            $current = Carbon::createFromDate($year, $month, 1);
            $target = $action === 'calendar_prev'
                ? $current->subMonth()
                : $current->addMonth();

            $this->sendDatePicker($user, $target);
            return;
        }

        if (str_starts_with($data, 'date_select_')) {
            $selectedDate = str_replace('date_select_', '', $data);
            $temp['date'] = $selectedDate;
            $user->update(['bot_state' => 'wizard_time', 'temp_data' => $temp]);
            $this->sendTimeInputPrompt($user, $selectedDate);
            return;
        }

        if ($data === 'time_manual') {
            $this->sendTimeInputPrompt($user, $temp['date'] ?? now()->format('Y-m-d'));
            return;
        }

        if ($data === 'task_manual') {
            $this->sendTitlePrompt($user, $temp['time'] ?? null);
            return;
        }

        if ($data === 'ai_suggest') {
            $this->sendAISuggestions($user);
            return;
        }

        if (str_starts_with($data, 'aitask_')) {
            $title = base64_decode(str_replace('aitask_', '', $data));
            $this->finalizeTask($user, $title);
            return;
        }
    }

    public function handleText(User $user, $text)
    {
        if ($user->bot_state === 'wizard_time') {
            $this->processTimeInput($user, trim($text));
            return;
        }

        if ($user->bot_state === 'wizard_title') {
            $this->finalizeTask($user, trim($text));
            return;
        }
    }

    protected function sendDatePicker(User $user, ?Carbon $month = null)
    {
        $month = $month ? $month->copy()->firstOfMonth() : Carbon::now()->firstOfMonth();
        $keyboard = $this->buildCalendarKeyboard($month);
        $this->telegram->sendMessage($user->chat_id, "🗓 <b>Sana tanlang</b>\n\nIltimos, kerakli kun ustiga bosing.", $keyboard);
    }

    protected function buildCalendarKeyboard(Carbon $month)
    {
        $monthNames = [
            1 => 'Yanvar', 2 => 'Fevral', 3 => 'Mart', 4 => 'Aprel',
            5 => 'May', 6 => 'Iyun', 7 => 'Iyul', 8 => 'Avgust',
            9 => 'Sentyabr', 10 => 'Oktyabr', 11 => 'Noyabr', 12 => 'Dekabr',
        ];

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '◀️', 'callback_data' => 'calendar_prev_' . $month->format('Y') . '_' . $month->format('n')],
                    ['text' => $monthNames[$month->month] . ' ' . $month->year, 'callback_data' => 'calendar_header'],
                    ['text' => '▶️', 'callback_data' => 'calendar_next_' . $month->format('Y') . '_' . $month->format('n')],
                ],
            ],
        ];

        $weekDays = ['Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh', 'Ya'];
        $weekdayRow = [];
        foreach ($weekDays as $day) {
            $weekdayRow[] = ['text' => $day, 'callback_data' => 'calendar_header'];
        }
        $keyboard['inline_keyboard'][] = $weekdayRow;

        $firstWeekDay = $month->dayOfWeek;
        $row = [];

        // Adjust for Monday start in Uzbekistan (Carbon's dayOfWeek is 0 for Sunday, 1 for Monday, etc.)
        $adjustedFirstWeekDay = ($firstWeekDay === 0) ? 6 : ($firstWeekDay - 1);

        for ($i = 0; $i < $adjustedFirstWeekDay; $i++) {
            $row[] = ['text' => ' ', 'callback_data' => 'calendar_header'];
        }

        $daysInMonth = $month->daysInMonth;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $month->copy()->day($day)->format('Y-m-d');
            $row[] = ['text' => (string) $day, 'callback_data' => 'date_select_' . $date];

            if (count($row) === 7) {
                $keyboard['inline_keyboard'][] = $row;
                $row = [];
            }
        }

        if (!empty($row)) {
            while (count($row) < 7) {
                $row[] = ['text' => ' ', 'callback_data' => 'calendar_header'];
            }
            $keyboard['inline_keyboard'][] = $row;
        }

        return $keyboard;
    }

    protected function sendTimeInputPrompt(User $user, string $date)
    {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '⌨️ Vaqtni kiriting', 'callback_data' => 'time_manual']]
            ]
        ];

        $this->telegram->sendMessage(
            $user->chat_id,
            "⏰ <b>{$date}</b> uchun vaqtni kiriting.\nFormat: <code>HH:MM</code>\nMasalan: <i>14:37</i>",
            $keyboard
        );
    }

    protected function processTimeInput(User $user, string $text)
    {
        if (!preg_match('/^\d{2}:\d{2}$/', $text)) {
            $this->telegram->sendMessage($user->chat_id, "⚠️ Noto'g'ri format. Iltimos, vaqtni <b>HH:MM</b> formatida kiriting.\nMasalan: <i>18:10</i>");
            return;
        }

        try {
            $date = $user->temp_data['date'] ?? Carbon::now()->format('Y-m-d');
            $scheduledAt = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$text}");
        } catch (\Exception $e) {
            $this->telegram->sendMessage($user->chat_id, "⚠️ Vaqtni qayta tekshiring. Iltimos, <b>HH:MM</b> formatida kiriting.");
            return;
        }

        if (!$scheduledAt || $scheduledAt->isPast()) {
            $this->telegram->sendMessage($user->chat_id, "⚠️ Bu vaqt o'tib ketgan. Iltimos, kelajakdagi vaqtni kiriting.");
            return;
        }

        $temp = $user->temp_data;
        $temp['time'] = $text;
        $user->update(['bot_state' => 'wizard_title', 'temp_data' => $temp]);
        $this->sendTitlePrompt($user, $text);
    }

    protected function sendTitlePrompt(User $user, ?string $time)
    {
        $temp = $user->temp_data ?? [];
        $prefilledTask = $temp['task'] ?? null;

        $message = "📝 Vazifa nomini kiriting.\n";
        if ($time) {
            $message .= "Vaqt: <b>{$time}</b>\n\n";
        }
        $message .= "Masalan: <i>Kitob o'qish</i>";

        if ($prefilledTask) {
            $message .= "\n\nSiz tanlagan AI taklifi: <b>{$prefilledTask}</b>\nAgar shu nom yaxshi bo'lsa, xabar yuboring yoki o'zgartiring.";
        }

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '⌨️ Qo‘lda yozish', 'callback_data' => 'task_manual']],
                [['text' => '🤖 AI yordam', 'callback_data' => 'ai_suggest']]
            ]
        ];

        $this->telegram->sendMessage($user->chat_id, $message, $keyboard);
    }

    public function sendAISuggestions(User $user)
    {
        $suggestions = [
            "Kitob o'qish",
            "Sport bilan shug'ullanish",
            "Namozga tayyorlanish",
            "Dasturlash bo'yicha mashq"
        ];

        $keyboard = ['inline_keyboard' => []];
        foreach ($suggestions as $suggestion) {
            $keyboard['inline_keyboard'][] = [
                [['text' => $suggestion, 'callback_data' => 'aitask_' . base64_encode($suggestion)]]
            ];
        }

        $this->telegram->sendMessage($user->chat_id, "🤖 AI tavsiyalari. Birini tanlang yoki o'zingiz nom kiriting.", $keyboard);
    }

    protected function finalizeTask(User $user, $title)
    {
        $date = $user->temp_data['date'] ?? null;
        $time = $user->temp_data['time'] ?? null;

        if (!$date || !$time) {
            $this->telegram->sendMessage($user->chat_id, "⚠️ Sana yoki vaqt topilmadi. Iltimos, qaytadan boshlang.");
            $user->update(['bot_state' => null, 'temp_data' => null]);
            return;
        }

        try {
            $scheduledAt = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}");
        } catch (\Exception $e) {
            $this->telegram->sendMessage($user->chat_id, "⚠️ Sana va vaqt noto'g'ri. Iltimos, qaytadan boshlang.");
            $user->update(['bot_state' => null, 'temp_data' => null]);
            return;
        }

        $user->tasks()->create([
            'chat_id' => $user->chat_id,
            'task' => $title,
            'title' => $title,
            'datetime' => $scheduledAt,
            'scheduled_at' => $scheduledAt,
            'status' => 'pending'
        ]);

        $user->update(['bot_state' => null, 'temp_data' => null]);
        $this->telegram->sendMessage($user->chat_id, "✅ Zo'r! Vazifa saqlandi:\n<b>{$title}</b>\n⏰ {$scheduledAt->format('Y-m-d H:i')}");
    }
}
