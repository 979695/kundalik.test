<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use App\Services\NotificationService;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Har daqiqada bildirishnomalarni tekshirish
Schedule::call(function (NotificationService $service) {
    $service->checkAndNotify();
})->everyMinute();
