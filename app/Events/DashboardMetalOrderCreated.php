<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class DashboardMetalOrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public $order){}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('dashboard'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'metal-order-created';
    }

    public function broadcastWith(): array
    {
        // Product ممکن است آرایه یا مدل Eloquent باشد
        $product = $this->order->product;

        $creator = $this->order->creator; // relation morphTo

        return [
            'id' => $this->order->id,
            'tracking_code' => $this->order->tracking_code,
            'order_type' => $this->order->order_type,
            'created_type' => $this->order->created_type,
            'status' => $this->order->status,

            'product' => [
                'name'     => data_get($product, 'name'),
                'quantity' => data_get($product, 'quantity'),
            ],

            // creator فقط فیلدهای موجود را ارسال می‌کنیم
            'creator' => array_filter([
                'id'   => data_get($creator, 'id'),
                'name' => data_get($creator, 'name'),
                'code' => data_get($creator, 'code'),
            ], fn ($v) => !is_null($v)),
        ];
    }

}
