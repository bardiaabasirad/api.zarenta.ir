<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'vat' => $this->vat,
            'created_by' => $this->created_by,
            'gold_carat' => $this->gold_carat,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'credit_payment' => $this->credit_payment,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'categories' => $this->categories->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                ];
            }),
            'directories' => $this->directories->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                ];
            }),
            'varieties' => $this->varieties->map(function ($variety) {
                return [
                    'id' => $variety->id,
                    'weight' => $variety->weight,
                    'count' => $variety->count,
                    'size' => $variety->size,
                    'gold_price' => $variety->gold_price,
                    'product_id' => $variety->product_id,
                    'created_at' => $variety->created_at,
                ];
            }),
            'images' => $this->images,
            'size_unit' => $this->size_unit,
            'properties' => $this->properties->map(function ($property) {
                return [
                    'id' => $property->id,
                    'title' => $property->title,
                    'selected_values' => $property->pivot->values,
//                    'values' => $property->values,
                ];
            }),
        ];
    }
}
