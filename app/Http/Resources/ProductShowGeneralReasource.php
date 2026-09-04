<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductShowGeneralReasource extends JsonResource
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
            'gold_carat' => $this->gold_carat,
            'title' => $this->title,
            'vat' => $this->vat,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'imageVariety' => $this->imageVariety,
            'varieties' => $this->varieties->map(function ($variety) {
                return [
                    'id' => $variety->id,
                    'weight' => $variety->weight,
                    'size' => $variety->size,
                    'count' => $variety->count,
                    'percentage_buy_wage' => $variety->percentage_buy_wage,
                    'tomans_buy_wage' => $variety->tomans_buy_wage,
                    'percentage_sell_wage' => $variety->percentage_sell_wage,
                    'tomans_sell_wage' => $variety->tomans_sell_wage,
                    'percentage_profit' => $variety->percentage_profit,
                    'tomans_profit' => $variety->tomans_profit,
                    'percentage_discount' => $variety->percentage_discount,
                    'tomans_discount' => $variety->tomans_discount,
                    'color' => $variety->color? [
                        'id' => $variety->color->id,
                        'color_name' => $variety->color->color_name,
                        'hex_code' => $variety->color->hex_code,
                    ]:null,
                    'images' => $variety->images->map(function ($image) {
                        return [
                            'id' => $image->id,
                            'image' => $image->image,
                            'pivot' => $image->pivot,
                        ];
                    }),
                ];
            }),
            'properties' => $this->properties->map(function ($property) {
                return [
                    'id' => $property->id,
                    'title' => $property->title,
                    'values' => $property->pivot->values,
                ];
            }),
            'size_unit' => $this->size_unit,
        ];
    }
}
