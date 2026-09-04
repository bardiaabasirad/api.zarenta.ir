<?php

namespace App\Events;

use App\Models\Contact;
use App\Models\MetalItem;
use App\Models\MetalItemAutoOrderSetting;
use App\Models\MetalOrder;
use App\Models\Setting;
use App\Services\SelectedMetalPriceService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class SettingsChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(){}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('settings'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'changed';
    }

    public function broadcastWith()
    {
        $latestRawPriceFromSelectedSource = SelectedMetalPriceService::getLatestRawPriceFromSelectedSource();

        // یک کوئری واحد برای دریافت همه تنظیمات
        $allSettings = Setting::whereIn('option_key', [
            'market_status',
            'validity_period_of_melted_order_before_expires',
            'submit_outbound_gold_orders_by_bot',
            'auto_order_dispatch_enabled',
            'manual_order_review_duration_seconds',
            'melted_stock_quantity',
            'min_melted_stock_quantity',
            'max_melted_stock_quantity'
        ])->get()->keyBy('option_key');

        $generalKeys = [
            'validity_period_of_melted_order_before_expires',
            'manual_order_review_duration_seconds',
            'melted_stock_quantity',
            'min_melted_stock_quantity',
            'max_melted_stock_quantity',
        ];

        $generalSettings = collect($generalKeys)
            ->map(fn ($key) => $allSettings->get($key))
            ->filter() // حذف کلیدهایی که در دیتابیس نبودند (اختیاری)
            ->values();

        $metalOrders = MetalOrder::whereIn('status', ['pending', 'processing'])
            ->with([
                'leverageCheck',
                'creator',
                'metalOrderExchanges.priceSource'
            ])
            ->get();

        $redisKey = 'market_opening_sms:' . now()->format('Y-m-d');
        $isSMSSentToday = Redis::exists($redisKey) ? 'yes' : 'no';

        $metalItems = MetalItem::with(['selectedMetalPrices' => function ($query) {
            $query
                ->with(['priceSource' => function ($query) {
                    $query->select('id', 'name');
                }])
                ->orderBy('time', 'desc')
                ->limit(4);
        }, 'priceSourceMapping'])
            ->join('metal_item_groups', 'metal_items.metal_item_group_id', '=', 'metal_item_groups.id')
            ->orderBy('metal_item_groups.sort_order')
            ->orderBy('metal_items.sort_order')
            ->select('metal_items.id', 'metal_items.title', 'metal_items.is_buy_active', 'metal_items.is_sell_active', 'metal_items.price_change_threshold', 'metal_items.buy_sell_spread')
            ->get()
            ->map(function ($item) {
                $item->setRelation(
                    'selectedMetalPrices',
                    $item->selectedMetalPrices->reverse()->values()
                );
                return $item;
            });

        $contacts = Contact::orderBy('sort_order', 'asc')->get(['id', 'name', 'phone', 'sort_order']);

        return [
            'metal_items' => $metalItems,
            'metal_item_auto_order_settings' => MetalItemAutoOrderSetting::with(['metalItem','priceSource'])->get(),
            'auto_order_dispatch_enabled' => $allSettings->get('auto_order_dispatch_enabled'),
            'contacts' => $contacts,
            'latest_raw_price_from_selected_source' => $latestRawPriceFromSelectedSource,
            'market_status' => $allSettings->get('market_status'),
            'validity_period_of_melted_order_before_expires' => $allSettings->get('validity_period_of_melted_order_before_expires')?->option_value,
            'orders' => $metalOrders,
            'general_settings' => $generalSettings,
            'client_messages' => Redis::get('client:messages'),
            'submit_outbound_gold_orders_by_bot' => $allSettings->get('submit_outbound_gold_orders_by_bot'),
            'is_sms_sent_today' => $isSMSSentToday
        ];
    }
}
