<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SidebarUpdate implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public $type, public $sound = 'none'){  }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): Channel
    {
        return new Channel('sidebar');
    }

    public function broadcastAs()
    {
        return 'badge';
    }

    public function broadcastWith()
    {
        $count = match ($this->type) {
            'order' => \App\Models\Order::where('status', 'paid')->count(),
            'metal_order' => \App\Models\MetalOrder::where('status', 'pending')->count(),
            default => 0,
        };

        return [
            'type' => $this->type,
            'count' => $count,
            'sound' => $this->sound,
        ];
    }
}
