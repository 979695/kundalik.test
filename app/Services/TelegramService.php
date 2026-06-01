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
     * Markaziy audio yuborish funksiyasi
     */
    public function sendAudio($chatId, $audio, $caption = null, $keyboard = null)
    {
        $params = [
            'chat_id' => $chatId,
            'audio' => $audio,
            'parse_mode' => 'HTML'
        ];

        if ($caption) {
            $params['caption'] = $caption;
        }

        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }

        try {
            return $this->client->post('sendAudio', [
                'json' => $params
            ]);
        } catch (\Exception $e) {
            Log::error("Telegram sendAudio xatosi: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Markaziy voice (ovozli xabar) yuborish funksiyasi
     */
    public function sendVoice($chatId, $voice, $caption = null, $keyboard = null)
    {
        $params = [
            'chat_id' => $chatId,
            'voice' => $voice,
            'parse_mode' => 'HTML'
        ];

        if ($caption) {
            $params['caption'] = $caption;
        }

        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }

        try {
            return $this->client->post('sendVoice', [
                'json' => $params
            ]);
        } catch (\Exception $e) {
            Log::error("Telegram sendVoice xatosi: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Xabarni pin qilish funksiyasi
     */
    public function pinChatMessage($chatId, $messageId, $disableNotification = false)
    {
        try {
            return $this->client->post('pinChatMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'disable_notification' => $disableNotification,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("Telegram pinChatMessage xatosi: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Xabarni unpin qilish funksiyasi
     */
    public function unpinChatMessage($chatId, $messageId = null)
    {
        $params = ['chat_id' => $chatId];
        if ($messageId) {
            $params['message_id'] = $messageId;
        }

        try {
            return $this->client->post('unpinChatMessage', [
                'json' => $params
            ]);
        } catch (\Exception $e) {
            Log::error("Telegram unpinChatMessage xatosi: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Xabarni o'chirish funksiyasi
     */
    public function deleteMessage($chatId, $messageId)
    {
        try {
            return $this->client->post('deleteMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("Telegram deleteMessage xatosi: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Xabarni tahrirlash funksiyasi
     */
    public function editMessageText($chatId, $messageId, $text, $keyboard = null)
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }

        try {
            return $this->client->post('editMessageText', [
                'json' => $params
            ]);
        } catch (\Exception $e) {
            Log::error("Telegram editMessageText xatosi: " . $e->getMessage());
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
