<?php

namespace App\Jobs;

use App\Services\SupportTicketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * هر ساعت اجرا می‌شود و تیکت‌های پشتیبانی باز را که بیش از ۷ روز بدون پیام مانده‌اند
 * به‌صورت خودکار می‌بندد (AUTO_CLOSED).
 * اگر last_message_at خالی باشد، از created_at برای محاسبهٔ آن ۷ روز استفاده می‌شود.
 * با قفل کش از اجرای موازی تکراری در یک روز جلوگیری می‌کند.
 */
class AutoCloseStaleSupportTicketsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SupportTicketService $tickets): void
    {
        $date = now(config('app.timezone'))->toDateString();
        $lockKey = "job:support-tickets-auto-close:{$date}";

        $lock = Cache::lock($lockKey, 300);

        if (!$lock->get()) {
            return;
        }

        try {
            $count = $tickets->autoCloseStale(7);

            Log::info('AutoCloseStaleSupportTicketsJob completed', [
                'date' => $date,
                'closed' => $count,
            ]);
        } finally {
            optional($lock)->release();
        }
    }
}
