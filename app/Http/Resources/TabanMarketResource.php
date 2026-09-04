<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TabanMarketResource extends JsonResource
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
            'type' => 'taban',
            'price' => $this->price,
            'buy_price' => null,
            'prev_price' => $this->prev_price,
            'prev_buy_price' => null,
            'prev_day_price' => $this->prev_day_price,
            'prev_day_buy_price' => null,
            'prev_day_dollar' => $this->prev_day_dollar,
            'prev_day_ounce' => $this->prev_day_ounce,
            'prev_day_emam_coin' => $this->prev_day_emam_coin,
            'prev_day_full_coin' => $this->prev_day_full_coin,
            'prev_day_half_coin' => $this->prev_day_half_coin,
            'prev_day_quarter_coin' => $this->prev_day_quarter_coin,
            'ounce' => $this->ounce,
            'emam_coin' => $this->emam_coin,
            'full_coin' => $this->full_coin,
            'half_coin' => $this->half_coin,
            'quarter_coin' => $this->quarter_coin,
            'dollar' => $this->dollar,
            'euro' => $this->euro,
            'derham' => $this->derham,
            'read_at' => $this->read_at,
        ];
    }
}
