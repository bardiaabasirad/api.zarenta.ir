<?php

namespace App\Http\Resources;

use App\Constants\AppConstants;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RateRawResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $aliases = [
            AppConstants::TOMORROW               => 'tomorrow',
            AppConstants::DAY_AFTER_TOMORROW     => 'day_after_tomorrow',
            AppConstants::GOLD_COIN_86           => 'gold_coin_86',
            AppConstants::GOLD_HALF_COIN_86      => 'gold_half_coin_86',
            AppConstants::GOLD_QUARTER_COIN_86   => 'gold_quarter_coin_86',
            AppConstants::GOLD_COIN_OLD_VERSION  => 'gold_coin_old_version',
        ];

        if (!isset($aliases[$this->type])) {
            return [];
        }

        return [
            $aliases[$this->type] => [
                'id'         => $this->id,
                'buy'        => $this->buy,
                'sell'       => $this->sell,
                'created_at' => $this->created_at?->toDateTimeString(),
            ],
        ];
    }
}
