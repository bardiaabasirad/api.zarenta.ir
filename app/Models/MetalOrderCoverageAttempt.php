<?php

namespace App\Models;

use App\Constants\MetalCoverageAttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MetalOrderCoverageAttempt extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'sent_at' => 'datetime',
        'resolved_at' => 'datetime',
        'requested_qty' => 'string',
        'executed_qty' => 'string',
        'executed_rate' => 'string',
    ];

    public function metalOrder(): BelongsTo
    {
        return $this->belongsTo(MetalOrder::class);
    }

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(MetalOrderExchange::class, 'metal_order_exchange_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(MetalOrderCoverageAllocation::class, 'attempt_id');
    }

    public function actor(): MorphTo
    {
        return $this->morphTo('actor', 'actor_type', 'actor_id');
    }

    public function isFinalized(): bool
    {
        return MetalCoverageAttemptStatus::isFinal((string) $this->status);
    }
}
