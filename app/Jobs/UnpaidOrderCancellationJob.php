<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Events\OrderUpdated;
use App\Models\Order;
use App\Models\Variety;
use App\Services\OrderLogService;
use App\Services\VarietyLogService;
use Carbon\Carbon;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class UnpaidOrderCancellationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cutoffTime = Carbon::now();

        $orders = Order::where('status', OrderStatus::WAIT_PAYMENT)
            ->where('payment_deadline', '<', $cutoffTime)
            ->with('items')->get();

        DB::transaction(function () use ($cutoffTime, $orders) {
            foreach ($orders as $order) {

                OrderLogService::make(
                    $order->id,
                    ['status' => $order->status],
                    ['status' => OrderStatus::CANCELED]
                );

                foreach ($order->items as $item) {
                    $variety = Variety::find($item->variety_id);
                    if ($variety) {

                        VarietyLogService::make($variety, (string) ($variety->count + (int) $item->count), ['order_id' => (string) $order->id]);

                        $variety->increment('count', $item->count);
                        $variety->save();
                    }
                }

                $orderData = [
                    'id' => $order->id,
                    'code' => $order->code,
                    'status' => OrderStatus::CANCELED,
                    'user_id' => $order->user_id,
                    'sale' => $order->sale,
                    'purchase' => $order->purchase,
                    'preferred_shipping_method' => $order->preferred_shipping_method,
                    'user' => $order->user
                ];

                try {
                    event(new OrderUpdated($orderData));
                } catch (\Exception $exception){

                }
            }

            Order::where('status', OrderStatus::WAIT_PAYMENT)
                ->where('payment_deadline', '<', $cutoffTime)
                ->update([
                    'status' => OrderStatus::CANCELED,
                    'cancellation_reason' => 'پایان یافتن مهلت پرداخت',
                ]);
        });
    }
}
