<?php

namespace App\Events;

use App\Models\MetalOrder;
use App\Services\Metal\MetalOrderAssetRiskService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MetalOrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(){}

    public function broadcastOn(): Channel
    {
        return new Channel('metal-order');
    }

    public function broadcastAs(): string
    {
        return 'updated';
    }

    public function broadcastWith(): array
    {
        $metalOrders = MetalOrder::whereIn('status', ['pending', 'processing'])
            ->with([
                'leverageCheck',
                'creator',
                'metalOrderExchanges.priceSource'
            ])
            ->get();

        $metalOrders = app(MetalOrderAssetRiskService::class)->attachToOrders($metalOrders);

        return $metalOrders->values()->toArray();
    }
}
