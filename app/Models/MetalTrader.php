<?php

namespace App\Models;

use App\Observers\MetalTraderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[ObservedBy([MetalTraderObserver::class])]
class MetalTrader extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'password',
        'dealing_group_id',
        'asset_risk_threshold',
        'phone',
        'national_code',
        'address',
        'avatar',
        'market_opening_notification',
        'status',
        'last_seen',
        'last_login_at',
        'last_login_ip',
        'products_settings',
        'business_type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_seen' => 'datetime',
            'asset_risk_threshold' => 'float',
        ];
    }

    /**
     * Unique presence member id across guards (metal_trader-1 vs admin-1).
     * Without this, Reverb keys both as user_id "1" and suppresses member_added.
     */
    public function getAuthIdentifierForBroadcasting(): string
    {
        return 'metal_trader-'.$this->getAuthIdentifier();
    }

    public function dealingGroup()
    {
        return $this->belongsTo(DealingGroup::class, 'dealing_group_id');
    }

    public function assets()
    {
        return $this->hasMany(MetalTraderWallet::class);
    }

    public function spotFiatWallet()
    {
        // فرض می‌کنیم نام مدل کیف‌پول MetalTraderWallet است
        return $this->hasOne(MetalTraderWallet::class, 'metal_trader_id')
            ->whereNull('metal_item_id');
    }

    public function metalOrders()
    {
        return $this->morphMany(MetalOrder::class, 'created');
    }

    public function scopeWithHasPass(Builder $query): Builder
    {
        return $query->selectRaw("
        metal_traders.*,
        CASE
            WHEN password IS NOT NULL AND password <> '' THEN 1
            ELSE 0
        END AS has_pass
    ");
    }
}
