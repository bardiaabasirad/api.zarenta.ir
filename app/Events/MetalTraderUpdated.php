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
            'kimi_account_id' => $this->metalTrader->kimi_account_id,
            'api_key' => $this->metalTrader->api_key,
            'phone' => $this->metalTrader->phone,
            'balance' => $this->metalTrader->balance,
            'group' => $encryptedGroup,
            'inquiry_access' => $this->metalTrader->inquiry_access,
            'market_opening_notification' => $this->metalTrader->market_opening_notification,
            'aggregated_view_of_invoices' => $this->metalTrader->aggregated_view_of_invoices,
        ];
    }
}
