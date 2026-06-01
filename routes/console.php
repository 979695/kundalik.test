<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Har daqiqada bildirishnomalarni tekshirish
// Schedule::command ishonchli - DI closure o'rniga dedicated command ishlatiladi
Schedule::command('notify:check')->everyMinute();
