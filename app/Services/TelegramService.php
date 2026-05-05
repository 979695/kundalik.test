<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $token;
    protected $apiUrl;

    public function __construct()
    {
        $this->token = env('TELEGRAM_BOT_TOKEN');
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}";
    }

    /**
     * Markaziy xabar yuborish funksiyasi
     */
    public function sendMessage($chatId, $text, $keyboard = null)
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }

        return Http::post("{$this->apiUrl}/sendMessage", $params);
    }

    /**
     * Xabarlarni so'rab olish (Polling)
     */
    public function getUpdates($offset = 0)
    {
        $response = Http::get("{$this->apiUrl}/getUpdates", [
            'offset' => $offset,
            'timeout' => 30
        ]);

        return $response->successful() ? $response->json()['result'] : [];
    }
}
