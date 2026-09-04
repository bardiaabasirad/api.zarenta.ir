<?php

namespace App\Events;

use App\Models\SelectedMetalPrice;
use App\Services\EncryptionService;
use Carbon\Carbon;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SelectedMetalPriceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public SelectedMetalPrice $rate) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel
     */
    public function broadcastOn(): Channel
    {
        return new Channel('metal-prices');
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
        return EncryptionService::encrypt([
            'id' => $this->rate->id,
            'buy' => $this->rate->buy,
            'sell' => $this->rate->sell,
            'metal_item_id' => $this->rate->metal_item_id,
            'price_source_id' => $this->rate->price_source_id,
            'created_at' => Carbon::parse($this->rate->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
        ]);
    }
}
