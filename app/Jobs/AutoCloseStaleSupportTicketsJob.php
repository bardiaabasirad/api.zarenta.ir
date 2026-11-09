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
