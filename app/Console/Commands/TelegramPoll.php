<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\TelegramController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll';
    protected $description = 'Telegram bot xabarlarini so\'rab olish (Long Polling)';

    public function handle()
    {
        $this->info("Bot ishga tushdi... (To'xtatish uchun Ctrl+C bosing)");
        $token = env('TELEGRAM_BOT_TOKEN');
        $offset = 0;

        while (true) {
            $response = Http::get("https://api.telegram.org/bot{$token}/getUpdates", [
                'offset' => $offset,
                'timeout' => 30
            ]);

            if ($response->successful()) {
                $updates = $response->json()['result'];
                foreach ($updates as $update) {
                    $offset = $update['update_id'] + 1;
                    
                    // Controllerga so'rov yuboramiz
                    $controller = new TelegramController();
                    $controller->handle(new Request($update));
                    
                    $this->line("Xabar keldi: " . ($update['message']['text'] ?? 'Matnsiz xabar'));
                }
            }
            sleep(1);
        }
    }
}
