<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MetalOrderCoverageAllocation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'allocated_at' => 'datetime',
        'reversed_at' => 'datetime',
        'meta' => 'array',
        'qty' => 'string',
        'customer_unit_price' => 'string',
        'coverage_unit_cost' => 'string',
        'realized_pnl' => 'string',
    ];

    public function metalOrder(): BelongsTo
    {
        return $this->belongsTo(MetalOrder::class);
    }

    public function metalItem(): BelongsTo
    {
        return $this->belongsTo(MetalItem::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MetalTreasuryLot::class, 'lot_id');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(MetalOrderCoverageAttempt::class, 'attempt_id');
    }

    public function allocatedBy(): MorphTo
    {
        return $this->morphTo('allocatedBy', 'allocated_by_type', 'allocated_by_id');
    }

    public function isActive(): bool
    {
        return $this->reversed_at === null;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('reversed_at');
    }
}
