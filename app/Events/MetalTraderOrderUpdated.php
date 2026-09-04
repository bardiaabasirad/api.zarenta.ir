<?php

namespace App\Events;

use App\Models\MetalOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MetalTraderOrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MetalOrder $order){}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("order-{$this->order->tracking_code}"),
            new PrivateChannel("client-order-{$this->order->created_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Ensure $this->order and $this->order->orderable exist to avoid null reference errors
        if (!$this->order) {
            return [];
        }

        $extraData = $this->order->extra_data ?? [];

        return [
            'tracking_code' => $this->order->tracking_code,
            'order_type'    => $this->order->order_type,
            'status'        => $this->order->status,
            'product'       => $this->order->product,
            'frozen'        => $extraData['frozen'],
            'retry'         => $extraData['retry'] ?? 'inactive',
            'message'       => $this->order->status === 'rejected'
                ? ($extraData['message'] ?? '')
                : '',
            'created_at'    => $this->order->created_at,
        ];

    }
}
