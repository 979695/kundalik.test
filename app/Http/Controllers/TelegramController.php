<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\BotService;

use App\Models\DiaryEntry;

class TelegramController extends Controller
{
    protected $token;

    public function __construct()
    {
        $this->token = env('TELEGRAM_BOT_TOKEN');
    }

    /**
     * Telegramdan kelgan xabarlarni qabul qilish (Webhook)
     */
    public function handle(Request $request, BotService $botService)
    {
        $update = $request->all();
        $botService->handleUpdate($update);

        return response()->json(['status' => 'success']);
    }

    /**
     * Kundalik yozuvini saqlash
     */
    protected function saveEntry($chatId, $text)
    {
        DiaryEntry::create([
            'telegram_id' => $chatId,
            'content' => $text
        ]);

        $this->sendMessage($chatId, "✅ Saqlandi!");
    }

    /**
     * Oxirgi yozuvlarni ko'rsatish
     */
    protected function listEntries($chatId)
    {
        $entries = DiaryEntry::where('telegram_id', $chatId)
            ->latest()
            ->take(5)
            ->get();

        if ($entries->isEmpty()) {
            $this->sendMessage($chatId, "Hozircha hech qanday yozuv yo'q.");
            return;
        }

        $message = "<b>Sizning oxirgi yozuvlaringiz:</b>\n\n";
        foreach ($entries as $entry) {
            $date = $entry->created_at->format('d.m.Y H:i');
            $message .= "📅 <i>{$date}</i>\n📝 {$entry->content}\n\n";
        }

        $this->sendMessage($chatId, $message);
    }

    /**
     * Xabar yuborish funksiyasi
     */
    protected function sendMessage($chatId, $message)
    {
        if (!$this->token) {
            Log::error('Telegram Token topilmadi!');
            return;
        }

        Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * Webhookni o'rnatish uchun yordamchi metod (serverda kerak bo'ladi)
     */
    public function setWebhook()
    {
        if (!$this->token) {
            return "Xato: .env faylida TELEGRAM_BOT_TOKEN o'rnatilmagan!";
        }

        $url = env('APP_URL') . '/telegram/webhook';
        
        $response = Http::get("https://api.telegram.org/bot{$this->token}/setWebhook", [
            'url' => $url
        ]);

        return $response->json();
    }
}
