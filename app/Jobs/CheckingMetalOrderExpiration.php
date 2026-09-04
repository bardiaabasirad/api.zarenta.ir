<?php

namespace App\Jobs;

use App\Models\MetalOrder;
use App\Models\MetalOrderLog;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CheckingMetalOrderExpiration
{
    private int $validitySeconds;
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $cacheKey = 'setting:validity_period_of_melted_order_before_expires';

        $this->validitySeconds = Cache::rememberForever($cacheKey, function () {
            return (int) Setting::where('option_key', 'validity_period_of_melted_order_before_expires')
                ->value('option_value');
        });
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $expiredAtThreshold = now()->subSeconds($this->validitySeconds);

        $expiredOrders = MetalOrder::where('status', 'pending')
            ->where('created_at', '<', $expiredAtThreshold)
            ->get();

        foreach ($expiredOrders as $metalOrder) {
            DB::transaction(function () use ($metalOrder) {
                MetalOrderLog::create([
                    'metal_order_id' => $metalOrder->id,
                    'new_values' => [
                        'cause' => 'due_to_expiration',
                        'rejected_at' => Carbon::now(),
                        'message' => 'پایان یافتن زمان بررسی',
                    ],
                ]);

                $metalOrder->update([
                    'status' => 'rejected',
                    'extra_data' => array_merge($metalOrder->extra_data ?? [], [
                        'cause' => 'due_to_expiration',
                        'retry' => 'active',
                        'rejected_at' => Carbon::now(),
                        'message' => 'پایان یافتن زمان بررسی',
                    ])
                ]);
            });
        }
    }

}
