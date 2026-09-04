<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientPriceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "ounce" => $this->ounce,
            "price" => $this->price,
            "imami_coin" => $this->emam_coin,
            "bahar_coin" => $this->full_coin,
            "half_coin" => $this->half_coin,
            "quarter_coin" => $this->quarter_coin,
            "dollar" => $this->dollar,
            "euro" => $this->euro,
            "derham" => $this->derham,
            "time" => $this->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
        ];
    }
}
