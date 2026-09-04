<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RateResource extends JsonResource
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
            'buy' => $this->buy,
            'sell' => $this->sell,
            'ref_id' => $this->reference_channel_id,
            'ref_type' => $this->ref_type,
            'ref_name' => $this->referenceChannel?->channel_name,
            'created_at' => $this->created_at,
        ];
    }
}
