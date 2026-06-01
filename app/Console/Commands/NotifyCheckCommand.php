<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotifyCheckCommand extends Command
{
    protected $signature = 'notify:check';
    protected $description = 'Bildirishnomalarni tekshirish va yuborish';

    public function handle(NotificationService $notificationService)
    {
        $this->line('[' . now()->format('Y-m-d H:i:s') . '] Bildirishnomalar tekshirilmoqda...');
        Log::info('[notify:check] Ishga tushdi: ' . now()->toDateTimeString());

        try {
            $notificationService->checkAndNotify();
            $this->info('[' . now()->format('H:i:s') . '] Tekshiruv yakunlandi.');
            Log::info('[notify:check] Muvaffaqiyatli yakunlandi.');
        } catch (\Exception $e) {
            $this->error('Xatolik: ' . $e->getMessage());
            Log::error('[notify:check] Xatolik: ' . $e->getMessage());
        }

        return 0;
    }
}
