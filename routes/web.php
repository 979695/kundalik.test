<?php

use App\Http\Controllers\TelegramController;

Route::get('/', function () {
    return view('welcome');
});

// Telegram Webhook route
Route::post('/telegram/webhook', [TelegramController::class, 'handle']);

// Webhookni bir marta sozlash uchun route (brauzerda ochiladi)
Route::get('/telegram/set-webhook', [TelegramController::class, 'setWebhook']);
