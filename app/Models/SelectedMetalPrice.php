<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelectedMetalPrice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'buy'  => 'integer',
        'sell' => 'integer',
    ];

    public function priceSourceMapping(): BelongsTo
    {
        return $this->belongsTo(PriceSourceMapping::class, 'price_source_id', 'price_source_id')
            ->where('price_source_mappings.type', '=', $this->type);
    }

    public function priceSource()
    {
        return $this->belongsTo(PriceSource::class);
    }

    public function metalItem()
    {
        return $this->belongsTo(MetalItem::class);
    }
}
