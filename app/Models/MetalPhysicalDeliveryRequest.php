<?php

namespace App\Models;

use App\Constants\MetalPhysicalDeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetalPhysicalDeliveryRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'qty' => 'string',
        'delivered_qty' => 'string',
        'fee_rate_percent' => 'string',
        'fee_unit_price' => 'string',
        'fee_amount' => 'string',
        'fee_adjustment_amount' => 'string',
        'meta' => 'array',
        'fee_recognized_at' => 'datetime',
        'fee_refunded_at' => 'datetime',
        'prepared_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'rejected_at' => 'datetime',
        'metal_reserved_at' => 'datetime',
        'metal_released_at' => 'datetime',
        'metal_consumed_at' => 'datetime',
    ];

    public function trader(): BelongsTo
    {
        return $this->belongsTo(MetalTrader::class, 'metal_trader_id');
    }

    public function metalItem(): BelongsTo
    {
        return $this->belongsTo(MetalItem::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(MetalTraderWallet::class, 'metal_trader_wallet_id');
    }

    public function treasuryLot(): BelongsTo
    {
        return $this->belongsTo(MetalTreasuryLot::class, 'treasury_lot_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MetalPhysicalDeliveryEvent::class);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, MetalPhysicalDeliveryStatus::cancellable(), true);
    }
}
