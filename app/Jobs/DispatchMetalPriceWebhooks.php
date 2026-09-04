<?php

namespace App\Jobs;

use App\Models\DealingGroup;
use App\Models\MetalTrader;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class DispatchMetalPriceWebhooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly array $pricePayload,
    ) {
        $this->onQueue('webhook-dispatch');
    }

    public function handle(): void
    {
        $metalItemId = (int) $this->pricePayload['metal_item_id'];

        // ۱. دریافت شناسه گروه معاملاتی پیش‌فرض (سازگار با کش قبلی و جدید)
        $cachedValue = Cache::rememberForever(
            'setting.default_metal_trader_group_id',
            fn () => Setting::query()->where('option_key', 'default_metal_trader_group_id')->value('option_value')
        );

        $defaultGroupId = (int) ($cachedValue instanceof Setting ? $cachedValue->option_value : $cachedValue);

        // ۲. لود پیش‌دستانه و یک‌باره‌ی کانفیگ‌های گروه پیش‌فرض
        $defaultDealingGroupData = null;
        $defaultMetalItemConfigs = [];

        if ($defaultGroupId) {
            $defaultGroup = DealingGroup::query()
                ->with([
                    'metalItemConfigs' => fn ($query) => $query
                        ->where('metal_item_id', $metalItemId)
                        ->select([
                            'dealing_group_id',
                            'metal_item_id',
                            'tolerance_type',
                            'display_mode',
                            'min_order',
                            'max_order',
                            'buy_fee_margin',
                            'sell_fee_margin',
                        ]),
                ])
                ->select(['id', 'name'])
                ->find($defaultGroupId);

            if ($defaultGroup) {
                $defaultDealingGroupData = $defaultGroup->only(['id', 'name']);
                $defaultMetalItemConfigs = $defaultGroup->metalItemConfigs->toArray();
            }
        }

        // ۳. پردازش تریدرها به‌صورت Chunked
        MetalTrader::query()
            ->where('status', 'active')
            ->whereNotNull('webhook_url')
            ->where('webhook_url', '!=', '')
            ->where('webhook_enabled', true)
            ->select([
                'id',
                'dealing_group_id',
                'webhook_url',
                'webhook_secret',
                'webhook_secret_version',
            ])
            ->with([
                'dealingGroup:id,name',
                'dealingGroup.metalItemConfigs' => fn ($query) => $query
                    ->where('metal_item_id', $metalItemId)
                    ->select([
                        'dealing_group_id',
                        'metal_item_id',
                        'tolerance_type',
                        'display_mode',
                        'min_order',
                        'max_order',
                        'buy_fee_margin',
                        'sell_fee_margin',
                    ]),
            ])
            ->chunkById(200, function ($traders) use (
                $defaultDealingGroupData,
                $defaultMetalItemConfigs
            ) {
                foreach ($traders as $trader) {

                    $hasCustomGroup = $trader->dealingGroup !== null;

                    $dealingGroup = $hasCustomGroup
                        ? $trader->dealingGroup->only(['id', 'name'])
                        : $defaultDealingGroupData;

                    $metalItemConfigs = $hasCustomGroup
                        ? $trader->dealingGroup->metalItemConfigs->toArray()
                        : $defaultMetalItemConfigs;

                    if (empty($metalItemConfigs)) {
                        continue;
                    }

                    SendMetalPriceToTraderWebhook::dispatch(
                        pricePayload: $this->pricePayload,
                        trader: [
                            'id'                     => $trader->id,
                            'webhook_url'            => $trader->webhook_url,
                            'webhook_secret'         => $trader->webhook_secret,
                            'webhook_secret_version' => $trader->webhook_secret_version,
                            'webhook_enabled'        => true,
                            'dealing_group'          => $dealingGroup,
                            'metal_item_configs'     => $metalItemConfigs,
                        ],
                    );
                }
            });
    }
}
