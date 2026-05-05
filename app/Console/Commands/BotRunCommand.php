<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TelegramService;
use App\Services\BotService;
use App\Services\NotificationService;
use Carbon\Carbon;

class BotRunCommand extends Command
{
    protected $signature = 'bot:run';
    protected $description = 'Smart Life Assistant botini Long Polling orqali ishga tushirish';

    protected $telegram;
    protected $botService;
    protected $notificationService;

    public function __construct(
        TelegramService $telegram,
        BotService $botService,
        NotificationService $notificationService
    ) {
        parent::__construct();
        $this->telegram = $telegram;
        $this->botService = $botService;
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        $this->info("--------------------------------------------------");
        $this->info("  🌟 Smart Life Assistant Bot ishga tushdi 🌟    ");
        $this->info("--------------------------------------------------");

        $offset = 0;
        $lastNotificationCheck = Carbon::now();

        while (true) {
            try {
                // 1. Yangi xabarlarni tekshirish
                $updates = $this->telegram->getUpdates($offset);

                foreach ($updates as $update) {
                    $offset = $update['update_id'] + 1;
                    $this->botService->handleUpdate($update);
                    
                    $sender = $update['message']['from']['first_name'] ?? 'User';
                    $this->line("[" . now()->format('H:i:s') . "] Xabar keldi: {$sender}");
                }

                // 2. Bildirishnomalarni tekshirish (har 1 daqiqada)
                if (Carbon::now()->diffInMinutes($lastNotificationCheck) >= 1) {
                    $this->notificationService->checkAndNotify();
                    $lastNotificationCheck = Carbon::now();
                    $this->comment("[" . now()->format('H:i:s') . "] Bildirishnomalar tekshirildi.");
                }

            } catch (\Exception $e) {
                $this->error("Xatolik yuz berdi: " . $e->getMessage());
                sleep(5);
            }

            usleep(500000); // 0.5 soniya tanaffus
        }
    }
}
