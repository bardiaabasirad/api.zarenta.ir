<?php

namespace App\Models;

use App\Constants\MetalTreasuryLotStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetalTreasuryLot extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'settlement_date' => 'date:Y-m-d',
        'purchased_at' => 'datetime',
        'meta' => 'array',
        'initial_qty' => 'string',
        'remaining_qty' => 'string',
        'unit_cost' => 'string',
    ];

    public function metalItem(): BelongsTo
    {
        return $this->belongsTo(MetalItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(MetalTreasuryMovement::class, 'lot_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(MetalOrderCoverageAllocation::class, 'lot_id');
    }

    public function isOpen(): bool
    {
        return $this->status === MetalTreasuryLotStatus::OPEN;
    }

    /**
     * مقدار آزاد قابل خروج خارجی (نه تخصیص‌یافته به سفارش آنلاین).
     * remaining_qty همین مقدار آزاد است؛ تخصیص‌ها از قبل از remaining کم شده‌اند.
     */
    public function freeQty(): string
    {
        return (string) $this->remaining_qty;
    }
}
