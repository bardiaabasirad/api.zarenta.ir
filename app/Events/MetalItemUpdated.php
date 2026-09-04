<?php

namespace App\Events;

use App\Models\MetalItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MetalItemUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public MetalItem $metalItem) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel
     */
    public function broadcastOn(): Channel
    {
        return new Channel('metal-items');
    }

    public function broadcastAs(): string
    {
        return 'changed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
//    public function broadcastWith(): array
//    {
//        return [
//            'id' => $this->selectedMetalPrice->id,
//            'price_source' => $this->selectedMetalPrice->priceSource,
//            'buy' => $this->selectedMetalPrice->buy,
//            'sell' => $this->selectedMetalPrice->sell,
//            'metal_item_id' => $this->selectedMetalPrice->metal_item_id,
//            'price_source_id' => $this->selectedMetalPrice->price_source_id,
//            'time' => Carbon::parse($this->selectedMetalPrice->time)->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
//            'created_at' => Carbon::parse($this->selectedMetalPrice->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
//            'updated_at' => Carbon::parse($this->selectedMetalPrice->updated_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
//        ];
//    }
}
