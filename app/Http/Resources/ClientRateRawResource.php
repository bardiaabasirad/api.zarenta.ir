<?php

namespace App\Http\Resources;

use App\Constants\AppConstants;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientRateRawResource extends JsonResource
{
    public $apiClient = null;
    public $settings = [];

    public static function collectionWithModel($resource, $settings = [], $apiClient = null)
    {
        return static::collection($resource)->map(fn ($item) => tap($item, function ($i) use ($settings, $apiClient) {
            $i->apiClient = $apiClient;
            $i->settings  = $settings;
        }));
    }

    public function toArray(Request $request): array
    {
        $apiClient = $this->apiClient ?? auth()->user();
        $mapping = [
            AppConstants::TOMORROW => [
                'keys' => ['today', 'tomorrow'],
                'prefixes' => ['today' => 'today_spot_settlement','tomorrow' => 'tomorrow_spot_settlement'],
            ],
            AppConstants::DAY_AFTER_TOMORROW => [
                'keys' => ['day_after_tomorrow'],
                'prefixes' => ['day_after_tomorrow' => 'day_after_tomorrow_spot_settlement'],
            ],
            AppConstants::GOLD_COIN_86 => [
                'keys' => ['gold_coin_86'],
                'prefixes' => ['gold_coin_86' => 'gold_coin_86'],
            ],
            AppConstants::GOLD_HALF_COIN_86 => [
                'keys' => ['gold_half_coin_86'],
                'prefixes' => ['gold_half_coin_86' => 'gold_half_coin_86'],
            ],
            AppConstants::GOLD_QUARTER_COIN_86 => [
                'keys' => ['gold_quarter_coin_86'],
                'prefixes' => ['gold_quarter_coin_86' => 'gold_quarter_coin_86'],
            ],
            AppConstants::GOLD_COIN_OLD_VERSION => [
                'keys' => ['gold_coin_old_version'],
                'prefixes' => ['gold_coin_old_version' => 'gold_coin_old_version'],
            ],
        ];

        $config = $mapping[$this->type] ?? null;
        if (!$config) {
            return [];
        }

        $output = [];
        foreach ($config['keys'] as $alias) {
            $prefix = $config['prefixes'][$alias];
            $products = $apiClient->products_settings ?? [];
            $output[$alias] = [
                'id'           => $this->id,
                'buy'          => $this->buy
                    ? $this->buy
                    + ($products["{$prefix}_sell_fee_margin"] ?? 0)
                    + ($this->settings["{$prefix}_buy_margin"] ?? 0)
                    : null,
                'sell'         => $this->sell
                    ? $this->sell
                    + ($products["{$prefix}_buy_fee_margin"] ?? 0)
                    + ($this->settings["{$prefix}_sell_margin"] ?? 0)
                    : null,
                'buy_status'   => $this->settings["{$prefix}_buy"] ?? null,
                'sell_status'  => $this->settings["{$prefix}_sell"] ?? null,
                'created_at'   => $this->created_at?->toDateTimeString(),
            ];
        }

        return $output;
    }
}
