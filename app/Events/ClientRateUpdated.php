<?php

namespace App\Events;

use App\Models\MetalTrader;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientRateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public $rates, public MetalTrader $user, public $marketStatus) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return PrivateChannel
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("rates-{$this->user->id}");
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
        return [
            'market_status' => $this->marketStatus,
            'rates' => $this->rates,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'kimi_account_id' => $this->user->kimi_account_id,
                'api_key' => $this->user->api_key,
                'phone' => $this->user->phone,
                'balance' => $this->user->balance,
                'limits' => [
                    'today_spot_settlement_min_order' => $this->user->products_settings['today_spot_settlement_min_order'],
                    'today_spot_settlement_max_order' => $this->user->products_settings['today_spot_settlement_max_order'],
                    'today_spot_settlement_status' => $this->user->products_settings['today_spot_settlement_status'],

                    'tomorrow_spot_settlement_min_order' => $this->user->products_settings['tomorrow_spot_settlement_min_order'],
                    'tomorrow_spot_settlement_max_order' => $this->user->products_settings['tomorrow_spot_settlement_max_order'],
                    'tomorrow_spot_settlement_status' => $this->user->products_settings['tomorrow_spot_settlement_status'],

                    'day_after_tomorrow_spot_settlement_min_order' => $this->user->products_settings['day_after_tomorrow_spot_settlement_min_order'],
                    'day_after_tomorrow_spot_settlement_max_order' => $this->user->products_settings['day_after_tomorrow_spot_settlement_max_order'],
                    'day_after_tomorrow_spot_settlement_status' => $this->user->products_settings['day_after_tomorrow_spot_settlement_status'],

                    'gold_coin_86_min_order' => $this->user->products_settings['gold_coin_86_min_order'],
                    'gold_coin_86_max_order' => $this->user->products_settings['gold_coin_86_max_order'],
                    'gold_coin_86_status' => $this->user->products_settings['gold_coin_86_status'],

                    'gold_half_coin_86_min_order' => $this->user->products_settings['gold_half_coin_86_min_order'],
                    'gold_half_coin_86_max_order' => $this->user->products_settings['gold_half_coin_86_max_order'],
                    'gold_half_coin_86_status' => $this->user->products_settings['gold_half_coin_86_status'],

                    'gold_quarter_coin_86_min_order' => $this->user->products_settings['gold_quarter_coin_86_min_order'],
                    'gold_quarter_coin_86_max_order' => $this->user->products_settings['gold_quarter_coin_86_max_order'],
                    'gold_quarter_coin_86_status' => $this->user->products_settings['gold_quarter_coin_86_status'],

                    'gold_coin_old_version_min_order' => $this->user->products_settings['gold_coin_old_version_min_order'],
                    'gold_coin_old_version_max_order' => $this->user->products_settings['gold_coin_old_version_max_order'],
                    'gold_coin_old_version_status' => $this->user->products_settings['gold_coin_old_version_status'],
                ],
                'inquiry_access' => $this->user->subscriptionNotExpired('inquiry'),
            ]
        ];
    }
}
