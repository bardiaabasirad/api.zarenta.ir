<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelMarketUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public $telMarketPrice){  }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): Channel
    {
        return new Channel('abshode');
    }

    public function broadcastAs(): string
    {
        return 'rate';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->telMarketPrice['id'],
            'buy' => $this->telMarketPrice['buy'],
            'sell' => $this->telMarketPrice['sell'],
            'delay' => $this->telMarketPrice['delay'],
            'reference' => [
                'id' => $this->telMarketPrice['reference']['id'],
                'channel_id' => $this->telMarketPrice['reference']['channel_id'],
                'channel_name' => $this->telMarketPrice['reference']['channel_name'],
            ],
            'time' => $this->telMarketPrice['time'],
            'created_at' => $this->telMarketPrice['created_at'],
        ];
    }
}
