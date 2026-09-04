<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AggregatedRateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    use InteractsWithSockets, SerializesModels;

    public function __construct(public $bucketKey, public $avg, public $min, public $max, public $last_sell, public $last_buy, public $count, public $timestamp, public $priceSource, public $marketStatus){}

    public function broadcastOn()
    {
        return new Channel('rates-aggregated');
    }

    public function broadcastWith()
    {
        return [
            'bucket' => $this->bucketKey,
            'avg' => $this->avg,
            'min' => $this->min,
            'max' => $this->max,
            'last' => $this->last_sell,
            'buy' => $this->last_buy,
            'count' => $this->count,
            'timestamp' => $this->timestamp,
            'price_source' => $this->priceSource,
            'market_status' => $this->marketStatus,
        ];
    }
}
