<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MetalTreasuryMovement extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
        'qty' => 'string',
        'unit_cost' => 'string',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MetalTreasuryLot::class, 'lot_id');
    }

    public function metalOrder(): BelongsTo
    {
        return $this->belongsTo(MetalOrder::class);
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(MetalOrderCoverageAllocation::class, 'allocation_id');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(MetalOrderCoverageAttempt::class, 'attempt_id');
    }

    public function actor(): MorphTo
    {
        return $this->morphTo('actor', 'actor_type', 'actor_id');
    }
}
