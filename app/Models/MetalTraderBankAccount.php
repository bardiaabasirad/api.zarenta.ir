<?php

namespace App\Models;

use App\Enums\BankAccountStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MetalTraderBankAccount extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = BankAccountStatus::PENDING;
    public const STATUS_APPROVED = BankAccountStatus::APPROVED;
    public const STATUS_REJECTED = BankAccountStatus::REJECTED;

    protected $fillable = [
        'metal_trader_id',
        'sheba',
        'status',
        'rejection_reason_id',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function trader(): BelongsTo
    {
        return $this->belongsTo(MetalTrader::class, 'metal_trader_id');
    }

    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(MetalTraderBankAccountRejectionReason::class, 'rejection_reason_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
