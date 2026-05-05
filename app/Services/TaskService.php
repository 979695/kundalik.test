<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

class TaskService
{
    /**
     * Yangi task qo'shish
     * Format: HH:MM | task_name
     */
    public function addTask(User $user, $text)
    {
        $parts = explode('|', $text);
        if (count($parts) < 2) return false;

        $time = trim($parts[0]);
        $title = trim($parts[1]);

        try {
            $scheduledAt = Carbon::createFromFormat('H:i', $time);
            
            // Agar vaqt o'tib ketgan bo'lsa, ertangi kunga rejalashtiramiz
            if ($scheduledAt->isPast()) {
                $scheduledAt->addDay();
            }

            return $user->tasks()->create([
                'title' => $title,
                'scheduled_at' => $scheduledAt,
                'status' => 'pending'
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Tasklarni ro'yxat qilish
     */
    public function listTasks(User $user)
    {
        return $user->tasks()
            ->where('status', 'pending')
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Taskni o'chirish
     */
    public function deleteTask(User $user, $taskId)
    {
        return $user->tasks()->where('id', $taskId)->delete();
    }

    /**
     * Bugungi avtomatik reja tuzish (AI feature simulation)
     */
    public function generateSmartSchedule(User $user)
    {
        $defaultTasks = [
            ['08:00', 'Ertalabki badantarbiya'],
            ['09:00', 'Ish/O\'qish boshlanishi'],
            ['13:00', 'Tushlik va dam olish'],
            ['18:00', 'Kunlik hisobot va rejalashtirish'],
            ['22:00', 'Uyquga tayyorgarlik']
        ];

        foreach ($defaultTasks as $item) {
            $this->addTask($user, "{$item[0]} | {$item[1]}");
        }

        return true;
    }
}
