<?php

namespace App\Services;

use App\Models\User;

class GamificationService
{
    const XP_PER_TASK = 10;
    const XP_PER_LEVEL = 100;

    public function rewardTaskCompletion(User $user)
    {
        $user->xp += self::XP_PER_TASK;
        
        // Level up logic
        if ($user->xp >= ($user->level * self::XP_PER_LEVEL)) {
            $user->level++;
            $user->xp = 0; // reset or keep overflowing logic
            return ['rewarded' => true, 'level_up' => true, 'new_level' => $user->level];
        }

        // Streak logic
        $user->streak++;
        $user->laziness_score = max(0, $user->laziness_score - 1);
        $user->save();

        return ['rewarded' => true, 'level_up' => false];
    }

    public function applyPunishment(User $user)
    {
        $punishments = [
            "🏋️‍♂️ 20 ta push-up qiling!",
            "📚 10 daqiqa kitob o'qing!",
            "🚰 Sovuq suv bilan yuzingizni yuving!",
            "🤲 Ozgina bo'lsa ham sadaqa qiling!",
            "🚶‍♂️ 15 daqiqa toza havoda sayr qiling!"
        ];

        $punishment = $punishments[array_rand($punishments)];

        // Increase laziness score
        $user->laziness_score += 5;
        $user->streak = 0; // reset streak
        $user->save();

        return $punishment;
    }
}
