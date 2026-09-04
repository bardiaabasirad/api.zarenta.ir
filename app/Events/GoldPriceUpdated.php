<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GoldPriceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $goldPrice, $channel;

    /**
     * Create a new event instance.
     *
     * @param $goldPrice
     * @param $channel
     */
    public function __construct($goldPrice, $channel)
    {
        $this->goldPrice = $goldPrice;
        $this->channel = $channel;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn(): array
    {
        return [
            new Channel($this->channel),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'gold.price.updated';
    }

    public function broadcastWith(): array
    {
        $fee = 0;
        if ($this->goldPrice->orderable->fee){
            $fee = $this->goldPrice->orderable->fee + $this->goldPrice->orderable->fee_margin;
        }

        $message = "";

        if (isset($this->goldPrice->extra_data['message'])){
            $message = $this->goldPrice->extra_data['message'];
        }

        return [
            'tracking_code' => $this->goldPrice->tracking_code,
            'order_type' => $this->goldPrice->order_type,
            'status' => $this->goldPrice->status,
            'weight' => $this->goldPrice->orderable->weight,
            'fee' => $fee,
            'message' => $message,
            'created_at' => $this->goldPrice->created_at,
        ];
    }
}
