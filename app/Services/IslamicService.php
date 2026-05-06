<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class IslamicService
{
    public function getPrayerTimes($city = 'Tashkent')
    {
        return Cache::remember("prayer_times_{$city}", 86400, function () use ($city) {
            $response = Http::get("http://api.aladhan.com/v1/timingsByCity", [
                'city' => $city,
                'country' => 'Uzbekistan',
                'method' => 2 // ISNA
            ]);

            if ($response->successful()) {
                return $response->json()['data']['timings'] ?? null;
            }
            return null;
        });
    }

    public function getDailyZikr()
    {
        $zikrs = [
            "SubhanAllah (100 marta)",
            "Alhamdulillah (100 marta)",
            "Allahu Akbar (100 marta)",
            "La ilaha illallah (100 marta)",
            "Astaghfirullah (100 marta)",
            "SubhanAllahi wa bihamdihi (100 marta)"
        ];

        return $zikrs[array_rand($zikrs)];
    }
}
