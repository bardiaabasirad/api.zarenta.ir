<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class DashboardMetalOrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public $order){}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
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
        return 'metal-order-updated';
    }

    public function broadcastWith(): array{
        return [
            'tracking_code' => $this->order->tracking_code,
            'status' => $this->order->status,
            'order_type' => $this->order->order_type,
            'product' => $this->order->product,
            'created_type' => $this->order->created_type,
            'creator' => $this->order->creator,
        ];
    }
}
