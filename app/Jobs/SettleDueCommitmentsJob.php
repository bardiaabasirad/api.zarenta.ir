<?php

namespace App\Jobs;

use App\Models\MetalOrder;
use App\Services\SettlementService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SettleDueCommitmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // ۳۰ دقیقه
    public int $tries = 1;

    public function handle(): void
    {
        $today = Carbon::today()->toDateString();

        $lock = Cache::lock("job:settlement:{$today}", 3600);
        if (! $lock->get()) {
            return;
        }

        try {
            // پیدا کردن تمام جفت‌های (trader_id, metal_item_id) که سفارش تسویه‌نشده برای امروز دارند
            $pendingPairs = MetalOrder::query()
                ->where('created_type', 'metal_trader')
                ->where('settlement_status', 'pending')
                ->whereDate('settlement_date', '<=', $today)
                ->select(['created_id as trader_id', 'metal_item_id'])
                ->distinct()
                ->get();

            Log::info("شروع عملیات تسویه سررسید تاریخ {$today}", [
                'total_positions' => $pendingPairs->count()
            ]);

            foreach ($pendingPairs as $pair) {
                try {
                    SettlementService::settleOrdersForTrader(
                        (int) $pair->trader_id,
                        (int) $pair->metal_item_id,
                        $today
                    );
                } catch (\Throwable $e) {
                    Log::error("خطا در تسویه تریدر #{$pair->trader_id} برای آیتم #{$pair->metal_item_id}: " . $e->getMessage());
                }
            }

            Log::info("پایان موفقیت‌آمیز عملیات تسویه سررسید تاریخ {$today}");
        } finally {
            optional($lock)->release();
        }
    }
}
