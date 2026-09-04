<?php

namespace App\Observers;

use App\Events\DashboardMetalOrderCreated;
use App\Events\DashboardMetalOrderUpdated;
use App\Events\MetalOrderUpdated;
use App\Events\MetalTraderOrderCreated;
use App\Events\MetalTraderOrderUpdated;
use App\Events\SidebarUpdate;
use App\Models\MetalOrder;
use App\Models\MetalTrader;
use Illuminate\Support\Facades\Log;

class MetalOrderObserver
{
    public $afterCommit = true;

    /**
     * Handle the MetalOrder "created" event.
     */
    public function created(MetalOrder $metalOrder): void
    {
        try {
            $metalOrder->load(['creator']);
            $metalOrder->updateProductFee();

            if ($metalOrder->status == 'pending') {
                MetalOrderUpdated::dispatch();
                SidebarUpdate::dispatch('metal_order', 'created');
            }

            if ($metalOrder->created_type == (new MetalTrader())->getMorphClass()) {
                MetalTraderOrderCreated::dispatch($metalOrder);
            }

            DashboardMetalOrderCreated::dispatch($metalOrder);

        } catch (\Exception $exception) {
            Log::error('MetalOrderObserver created: ' . $exception->getMessage());
        }
    }

    /**
     * Handle the MetalOrder "updated" event.
     */
    public function updated(MetalOrder $metalOrder): void
    {
        try {
            $metalOrder->load(['creator']);

            $metalOrder->updateProductFee();

            if ($metalOrder->created_type == (new MetalTrader())->getMorphClass()) {
                MetalTraderOrderUpdated::dispatch($metalOrder);
            }

            MetalOrderUpdated::dispatch();
            SidebarUpdate::dispatch('metal_order', 'completed');
            DashboardMetalOrderUpdated::dispatch($metalOrder);

        } catch (\Exception $exception) {
            Log::error('MetalOrderObserver updated: ' . $exception->getMessage());
        }
    }
}
