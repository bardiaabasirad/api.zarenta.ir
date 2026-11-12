<?php

namespace App\Events;

use App\Models\MetalTrader;
use App\Models\DealingGroup;
use App\Services\EncryptionService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MetalTraderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public MetalTrader $metalTrader) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return PrivateChannel
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("metal-trader-{$this->metalTrader->id}");
    }

    public function broadcastAs(): string
    {
        return 'updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        $encryptedGroup = EncryptionService::encrypt($this->metalTrader->dealingGroup);

        return [
            'id' => $this->metalTrader->id,
            'name' => $this->metalTrader->name,
            'phone' => $this->metalTrader->phone,
            'group' => $encryptedGroup,
            'market_opening_notification' => $this->metalTrader->market_opening_notification,
        ];
    }
}
