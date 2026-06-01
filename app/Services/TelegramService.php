<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class TelegramService
{
    protected $token;
    protected $apiUrl;
    protected $client;

    public function __construct()
    {
        $this->token = env('TELEGRAM_BOT_TOKEN');
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}";
        
        // Persistent connection configuration
        $this->client = new Client([
            'base_uri' => $this->apiUrl . '/',
            'timeout'  => 35.0,
            'headers'  => [
                'Connection' => 'keep-alive',
            ],
            'curl' => [
                CURLOPT_TCP_KEEPALIVE => 1,
            ],
        ]);
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

        try {
            return $this->client->post('sendMessage', [
                'json' => $params
            ]);
        } catch (\Exception $e) {
            Log::error("Telegram sendMessage xatosi: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Xabarlarni so'rab olish (Polling)
     */
    public function getUpdates($offset = 0)
    {
        try {
            $response = $this->client->get('getUpdates', [
                'query' => [
                    'offset' => $offset,
                    'timeout' => 30
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody()->getContents(), true);
                return $data['result'] ?? [];
            }
        } catch (\Exception $e) {
            Log::error("Telegram getUpdates xatosi: " . $e->getMessage());
        }

        return [];
    }
}
