<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetalPhysicalDeliveryEvent extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(MetalPhysicalDeliveryRequest::class, 'metal_physical_delivery_request_id');
    }
}
