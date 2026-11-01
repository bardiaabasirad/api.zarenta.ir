<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MetalTreasuryPhysicalPurchase extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'settlement_date' => 'date:Y-m-d',
        'meta' => 'array',
        'qty' => 'string',
        'unit_cost' => 'string',
    ];

    public function metalItem(): BelongsTo
    {
        return $this->belongsTo(MetalItem::class);
    }

    public function creator(): MorphTo
    {
        return $this->morphTo('creator', 'created_by_type', 'created_by_id');
    }

    public function lot(): HasOne
    {
        return $this->hasOne(MetalTreasuryLot::class, 'source_id')
            ->where('source_type', 'PHYSICAL_PURCHASE');
    }
}
