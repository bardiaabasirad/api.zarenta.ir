<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class MetalTraderWallet extends Model
{
    /**
     * ویژگی‌هایی که باید در آرایه‌سازی و خروجی JSON مدل همیشه پیوست شوند.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'total_balance',
    ];

    public function metalItem()
    {
        return $this->belongsTo(MetalItem::class, 'metal_item_id');
    }

    public function trader()
    {
        return $this->belongsTo(MetalTrader::class, 'metal_trader_id');
    }

    public function getIsFiatAttribute(): bool
    {
        return is_null($this->metal_item_id);
    }

    /**
     * اکسسور مربوط به محاسبه خودکار total_balance
     */
    protected function totalBalance(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                // اطمینان از نال نبودن و تبدیل به نوع عددی
                $available = (float) ($attributes['available_balance'] ?? 0);
                $blocked   = (float) ($attributes['blocked_balance'] ?? 0);

                return $available + $blocked;
            },
        );
    }
}
