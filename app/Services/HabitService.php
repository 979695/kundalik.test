<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\User;
use Carbon\Carbon;

class HabitService
{
    public function addHabit(User $user, $title, $frequency = 'daily')
    {
        return $user->habits()->create([
            'title' => $title,
            'frequency' => $frequency,
            'streak' => 0
        ]);
    }

    public function completeHabit(Habit $habit)
    {
        $habit->update([
            'last_completed_at' => Carbon::now(),
            'streak' => $habit->streak + 1
        ]);
        return $habit;
    }

    public function listHabits(User $user)
    {
        return $user->habits()->get();
    }
}
