<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetalTraderFiatWithdrawal extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'metal_trader_id',
        'metal_trader_bank_account_id',
        'sheba',
        'amount',
        'status',
        'wallet_transaction_id',
        'refund_wallet_transaction_id',
        'reject_reason',
        'rejection_reason_id',
        'reviewed_by',
        'reviewed_at',
        'notes',
        'reference_number',
        'created_by',
    ];

    protected $appends = [
        'source',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function trader(): BelongsTo
    {
        return $this->belongsTo(MetalTrader::class, 'metal_trader_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(MetalTraderBankAccount::class, 'metal_trader_bank_account_id');
    }

    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(MetalTraderFiatWithdrawalRejectionReason::class, 'rejection_reason_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function getSourceAttribute(): string
    {
        return $this->created_by ? 'admin' : 'trader';
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }

    public function refundWalletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'refund_wallet_transaction_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
