<?php

namespace App\Events;

use App\Models\MetalTrader;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class KimiBalanceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MetalTrader $metalTrader, public $balances){}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("balance-{$this->metalTrader->id}");
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'updated';
    }

    public function broadcastWith(): array
    {
        return $this->balances;
    }
}
