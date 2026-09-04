<?php

namespace App\Http\Resources;

use App\Models\SelectedMetalPrice;
use App\Models\PriceSourceMapping;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class RateThatNotAppliedResource extends JsonResource
{
    protected ?string $rateType = null;

    public function __construct($resource, $rateType = null)
    {
        parent::__construct($resource);
        $this->rateType = $rateType;
    }

    public function toArray($request): array
    {
        // به جای $this->type از $this->resource->type استفاده می‌کنیم
        $type = $this->rateType ?? $this->type ?? 'tomorrow';

        $refMarket = PriceSourceMapping::where('type', $type)
            ->where('reference_channel_id', $this->reference_channel_id ?? $this->ref_id)
            ->first();

        if (!$refMarket) {
            return [];
        }

        $variables = ['buy' => $this->buy, 'sell' => $this->sell];

        $rate = SelectedMetalPrice::make([
            'reference_channel_id' => $this->reference_channel_id,
            'buy' => $this->buy ? roundUpToThousand(calculateFormula($refMarket->buy, $variables)) : null,
            'sell' => $this->sell ? roundUpToThousand(calculateFormula($refMarket->sell, $variables)) : null,
            'ref_type' => 'telegram',
            'time' => $this->time,
        ]);

        return [
            'id'            => $this->id,
            'reference'     => $this->reference,
            'ref_type'      => $rate->ref_type,
            'type'          => $type,
            'buy'           => $rate->buy,
            'sell'          => $rate->sell,
            'time'          => Carbon::parse($this->time)->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'created_at'    => Carbon::parse($this->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'updated_at'    => Carbon::parse($this->updated_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
        ];
    }
}
