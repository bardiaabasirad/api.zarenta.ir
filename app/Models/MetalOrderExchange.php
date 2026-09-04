<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetalOrderExchange extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'details'     => 'array',
        'sent_at'     => 'datetime',
        'placed_at'   => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function priceSource()
    {
        return $this->belongsTo(PriceSource::class, 'price_source_id', 'id');
    }

    public function metalOrder()
    {
        return $this->belongsTo(MetalOrder::class, 'metal_order_id', 'id');
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, ['confirmed', 'rejected', 'error']);
    }
}
