<?php

namespace App\Jobs;

use App\Services\Metal\MetalOrderCoverageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MarkOverdueMetalOrderCoveragesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MetalOrderCoverageService $coverageService): void
    {
        $date = now(config('app.timezone'))->toDateString();
        $lockKey = "job:metal-coverage-overdue:{$date}";

        $lock = Cache::lock($lockKey, 300);

        if (!$lock->get()) {
            return;
        }

        try {
            $count = $coverageService->markOverdueOrders($date);

            Log::info('MarkOverdueMetalOrderCoveragesJob completed', [
                'date' => $date,
                'updated' => $count,
            ]);
        } finally {
            optional($lock)->release();
        }
    }
}
