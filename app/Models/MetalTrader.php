<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class MetalTrader extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'api_key',
        'password',
        'dealing_group_id',
        'kimi_account_id',
        'trade_leverage',
        'phone',
        'balance',
        'aggregated_view_of_invoices',
        'market_opening_notification',
        'status',
        'last_seen',
        'last_login_at',
        'last_login_ip',
        'products_settings',
        'business_type',
        'webhook_url',
        'webhook_secret',
        'webhook_secret_version',
        'webhook_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'webhook_secret'
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_seen' => 'datetime',
            'webhook_secret' => 'encrypted',
            'webhook_enabled' => 'boolean',
        ];
    }

    /**
     * Generate a unique API key
     *
     * @return string
     */
    public static function generateApiKey()
    {
        do {
            $api_key = Str::random(32);
        } while (self::where('api_key', $api_key)->exists());

        return $api_key;
    }

    public function hasFeature($featureSlug)
    {
        return $this->subscriptions()
            ->whereHas('subscriptionFeatures', function ($query) use ($featureSlug) {
                $query->where('slug', $featureSlug);
            })
            ->exists();
    }

    public function subscriptionNotExpired($featureSlug)
    {
        return $this->subscriptions()
            ->where('ends_at', '>', now())
            ->whereHas('subscriptionFeatures', function ($query) use ($featureSlug) {
                $query->where('slug', $featureSlug);
            })
            ->exists();
    }

    public function dealingGroup()
    {
        return $this->belongsTo(DealingGroup::class, 'dealing_group_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function metalOrders()
    {
        return $this->morphMany(MetalOrder::class, 'created');
    }

    public function inquiries()
    {
        return $this->belongsToMany(Inquiry::class);
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
