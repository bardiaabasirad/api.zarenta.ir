<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $guarded = ['id'];

    protected $appends = [
        'amount_unit',
        'signed_amount',
    ];

    /**
     * انواع تراکنشی که مقدارشان بر حسب تومان است
     * (حتی اگر روی کیف فلزی ثبت شده باشند).
     */
    public static function fiatTypes(): array
    {
        return [
            'deposit',
            'withdraw',
            'fiat_debt_in',
            'fiat_credit_in',
            'settlement_fiat_transfer',
            'penalty_loss',
            'settlement_correction_fiat',
        ];
    }

    public static function isFiatType(?string $type): bool
    {
        return in_array((string) $type, self::fiatTypes(), true);
    }

    protected function amountUnit(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (self::isFiatType($attributes['type'] ?? null)) {
                    return 'toman';
                }

                $unit = $attributes['metal_item_unit'] ?? null;

                return match ($unit) {
                    'gram', 'count' => $unit,
                    default => 'toman',
                };
            },
        );
    }

    /**
     * مقدار با علامت واقعی اثر روی موجودی (بدهی خرید منفی، طلب فروش مثبت).
     */
    protected function signedAmount(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $before = (string) ($attributes['balance_before'] ?? '0');
                $after = (string) ($attributes['balance_after'] ?? '0');
                $scale = self::isFiatType($attributes['type'] ?? null) ? 0 : 3;
                $signed = bcsub($after, $before, $scale);

                if (str_contains($signed, '.')) {
                    $signed = rtrim(rtrim($signed, '0'), '.');
                }

                return $signed === '-0' ? '0' : $signed;
            },
        );
    }

    public function metalOrder()
    {
        return $this->belongsTo(MetalOrder::class, 'metal_order_id');
    }

    public function settlementCase()
    {
        return $this->belongsTo(MetalSettlementCase::class, 'metal_settlement_case_id');
    }
}
