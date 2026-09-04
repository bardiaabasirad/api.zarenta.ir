<?php

namespace App\Events;

use App\Models\MetalOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MetalTraderOrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MetalOrder $order){}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("client-order-{$this->order->created_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'created';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Ensure $this->order exist to avoid null reference errors
        if (!$this->order) {
            return [];
        }

        // Simplify message logic for rejected orders
        $message = $this->order->status === 'rejected'
            ? ($this->order->extra_data['message'] ?? 'سفارش شما رد شد')
            : '';

        return [
            'tracking_code' => $this->order->tracking_code,
            'order_type' => $this->order->order_type,
            'status' => $this->order->status,
            'product' => $this->order->product,
            'frozen' => $this->order->extra_data['frozen']??'',
            'message' => $message,
            'created_at' => $this->order->created_at,
        ];
    }
}
