<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetalSettlementCase extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'settlement_date' => 'date:Y-m-d',
        'net_metal' => 'string',
        'net_fiat' => 'string',
        'cash_balance_after' => 'string',
        'spot_metal_balance_after' => 'string',
        'penalty_one_percent' => 'string',
        'replacement_loss' => 'string',
        'penalty_total' => 'string',
        'partner_trade_used' => 'boolean',
        'extra_data' => 'array',
        'fulfilled_at' => 'datetime',
        'defaulted_at' => 'datetime',
    ];

    public function trader(): BelongsTo
    {
        return $this->belongsTo(MetalTrader::class, 'metal_trader_id');
    }

    public function commitmentMetalItem(): BelongsTo
    {
        return $this->belongsTo(MetalItem::class, 'commitment_metal_item_id');
    }

    public function spotMetalItem(): BelongsTo
    {
        return $this->belongsTo(MetalItem::class, 'spot_metal_item_id');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(
            MetalOrder::class,
            'metal_settlement_case_order',
            'metal_settlement_case_id',
            'metal_order_id'
        )->withTimestamps();
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'metal_settlement_case_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
