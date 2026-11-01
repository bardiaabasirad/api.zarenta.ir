<?php

namespace App\Events;

use App\Models\MetalTraderNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MetalTraderNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MetalTraderNotification $notification) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('metal-trader-' . $this->notification->metal_trader_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'trader-notification-created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'title' => $this->notification->title,
            'content' => $this->notification->content,
            'link' => $this->notification->link,
            'type' => $this->notification->type,
            'created_at' => $this->notification->created_at,
        ];
    }
}
