<?php

namespace App\Events;

use App\Models\RawMetalPrice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LatestRawMetalPriceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public RawMetalPrice $rawMetalPrice) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel
     */
    public function broadcastOn(): Channel
    {
        return new Channel('latest-raw-metal-prices');
    }

    public function broadcastAs(): string
    {
        return 'updated';
    }

    public function broadcastWith(): array
    {
        return [
            "id" => $this->rawMetalPrice->id,
            "price_source_id" => $this->rawMetalPrice->price_source_id,
            "metal_item_id" => $this->rawMetalPrice->metal_item_id,
            "buy" => $this->rawMetalPrice->buy,
            "sell" => $this->rawMetalPrice->sell,
            "time" => $this->rawMetalPrice->time,
            "created_at" => $this->rawMetalPrice->created_at,
            "updated_at" => $this->rawMetalPrice->updated_at,
            "metal_title" => $this->rawMetalPrice->metalItem->title,
            "source_name" => $this->rawMetalPrice->priceSource->name,
        ];
    }
}
