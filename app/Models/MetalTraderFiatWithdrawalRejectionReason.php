<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetalTraderFiatWithdrawalRejectionReason extends Model
{
    protected $fillable = [
        'value',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function withdrawals(): HasMany
    {
        return $this->hasMany(MetalTraderFiatWithdrawal::class, 'rejection_reason_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
