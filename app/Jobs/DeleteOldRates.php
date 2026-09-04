<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeleteOldRates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle()
    {
        $twoDaysAgo = Carbon::now()->subDays(2);

        try {
            // شناسایی آخرین ID هر metal_item_id در جدول selected_metal_prices
            $latestSelectedPriceIds = DB::table('selected_metal_prices')
                ->select('metal_item_id', DB::raw('MAX(id) as latest_id'))
                ->groupBy('metal_item_id')
                ->pluck('latest_id')
                ->toArray();

            // حذف از جدول selected_metal_prices (به جز آخرین نرخ‌ها)
            $deletedSelectedMetalPrices = DB::table('selected_metal_prices')
                ->where('created_at', '<', $twoDaysAgo)
                ->whereNotIn('id', $latestSelectedPriceIds)
                ->delete();

            Log::info("DeleteOldRates: {$deletedSelectedMetalPrices} رکورد از جدول selected_metal_prices حذف شد.");

            // شناسایی آخرین ID هر metal_item_id در جدول raw_metal_prices
            $latestRawPriceIds = DB::table('raw_metal_prices')
                ->select('metal_item_id', DB::raw('MAX(id) as latest_id'))
                ->groupBy('metal_item_id')
                ->pluck('latest_id')
                ->toArray();

            // حذف از جدول raw_metal_prices (به جز آخرین نرخ‌ها)
            $deletedRawMetalPrices = DB::table('raw_metal_prices')
                ->where('created_at', '<', $twoDaysAgo)
                ->whereNotIn('id', $latestRawPriceIds)
                ->delete();

            Log::info("DeleteOldRates: {$deletedRawMetalPrices} رکورد از جدول raw_metal_prices حذف شد.");

            return [
                'selected_metal_prices' => $deletedSelectedMetalPrices,
                'raw_metal_prices' => $deletedRawMetalPrices,
            ];

        } catch (\Exception $e) {
            Log::error("خطا در حذف رکوردهای قدیمی: " . $e->getMessage());
            throw $e;
        }

    }
}
