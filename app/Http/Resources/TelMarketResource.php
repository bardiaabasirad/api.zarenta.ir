<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TelMarketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'telegram',
            'price' => $this->sell,
            'buy_price' => $this->buy,
            'prev_price' => null,
            'prev_buy_price' => null,
            'prev_day_price' => $this->prev_sell,
            'prev_day_buy_price' => $this->prev_buy,
            'prev_day_dollar' => null,
            'prev_day_ounce' => null,
            'prev_day_emam_coin' => null,
            'prev_day_full_coin' => null,
            'prev_day_half_coin' => null,
            'prev_day_quarter_coin' => null,
            'ounce' => null,
            'emam_coin' => null,
            'full_coin' => null,
            'half_coin' => null,
            'quarter_coin' => null,
            'dollar' => null,
            'euro' => null,
            'derham' => null,
            'read_at' => $this->time,
        ];
    }
}
