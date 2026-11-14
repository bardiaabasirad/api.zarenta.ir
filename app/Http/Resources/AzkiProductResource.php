<?php

namespace App\Http\Resources;

use App\Models\MarketPrice;
use App\Models\Setting;
use App\Services\ProductPriceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AzkiProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $marketPrice = MarketPrice::latest()->first();
        $vat = Setting::where('option_key', 'value_added_tax')->first();

        return [
            'merchant' => 'طلای ' . config('app.name'),
            'name' => $this->title,
            'category_name' => $this->categories->isNotEmpty() ? $this->categories->first()->title : 'بدون دسته بندی',
            'description' => $this->description,
            'image_url' => 'https://api.zhikgold.ir/api/v1/images/' . $this->images->first()?->image,
            'listing_url' => 'https://zhikgold.ir/products/' . $this->id,
            'ads_id' => $this->id,
            'availability' => 'موجود',
            'price_before_discount' => ProductPriceService::priceWithoutDiscount(
                tap($this->variety, fn ($variety) => $variety?->setRelation('product', $this->resource)),
                $marketPrice,
                $this->vat,
                $vat->option_value
            ),
            'total_price' => ProductPriceService::priceWithDiscount(
                tap($this->variety, fn ($variety) => $variety?->setRelation('product', $this->resource)),
                $marketPrice,
                $this->vat,
                $vat->option_value
            ),
        ];
    }
}
