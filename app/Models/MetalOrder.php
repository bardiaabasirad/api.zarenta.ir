<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class MetalOrder extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'extra_data' => 'array',
        'product' => 'array',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function extraData(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $array = json_decode($value, true);

                if (isset($array['extra_data']) && is_string($array['extra_data'])) {
                    $decodedExtraData = json_decode($array['extra_data'], true);
                    $array['extra_data'] = $decodedExtraData ?? $array['extra_data'];
                }

                return $array;
            },
        );
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, ['succeed', 'rejected']);
    }

    public function product(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $array = json_decode($value, true);

                if (isset($array['product']) && is_string($array['product'])) {
                    $decodedExtraData = json_decode($array['product'], true);
                    $array['product'] = $decodedExtraData ?? $array['product'];
                }

                return $array;
            },
        );
    }

    public function leverageCheck()
    {
        return $this->hasOne(MetalOrderLeverageCheck::class);
    }

    public function updateProductFee(): self
    {
        $this->updateArrayField('product', function ($product) {

            if (isset($product['tolerance_type']) && $product['tolerance_type'] == 'percentage') {
                // اگر هم fee و هم fee_margin وجود داشته باشد
                if (isset($product['fee']) && isset($product['fee_margin'])) {
                    $product['fee'] = $product['fee'] + $product['fee'] * $product['fee_margin'] / 100;
                    if (isset($product['new_fee'])) {
                        $product['new_fee'] = $product['new_fee'] + $product['new_fee'] * $product['fee_margin'] / 100;
                    }
                }
            } else {
                // اگر هم fee و هم fee_margin وجود داشته باشد
                if (isset($product['fee']) && isset($product['fee_margin'])) {
                    $product['fee'] = $product['fee'] + $product['fee_margin'];
                    if (isset($product['new_fee'])) {
                        $product['new_fee'] = $product['new_fee'] + $product['fee_margin'];
                    }
                }
            }

            // حذف fee_margin در هر صورت
            unset($product['fee_margin']);

            return $product;
        });

        return $this;
    }

    /**
     * محاسبه‌ی fee نهایی (اعمال fee_margin) بدون تغییر state مدل.
     * این متد pure است: هیچ چیزی را persist یا حذف نمی‌کند و برای فراخوانی
     * چندباره (retry) امن است.
     *
     * @return array{fee: float|null, new_fee: float|null}
     */
    public function computeEffectiveFee(): array
    {
        $product = $this->product ?? [];

        $applyMargin = static function (?float $base, ?float $margin, ?string $toleranceType): ?float {
            if ($base === null || $margin === null) {
                return $base;
            }

            // در حالت درصدی، margin نسبتی از base است؛ در غیر این صورت مقدار مطلق.
            if ($toleranceType === 'percentage') {
                return $base + ($base * $margin / 100);
            }

            return $base + $margin;
        };

        $feeMargin      = isset($product['fee_margin']) ? (float) $product['fee_margin'] : null;
        $toleranceType  = $product['tolerance_type'] ?? null;

        $fee = isset($product['fee'])
            ? $applyMargin((float) $product['fee'], $feeMargin, $toleranceType)
            : null;

        $newFee = isset($product['new_fee'])
            ? $applyMargin((float) $product['new_fee'], $feeMargin, $toleranceType)
            : null;

        return [
            'fee'     => $fee,
            'new_fee' => $newFee,
        ];
    }

    public function updateArrayField(string $fieldName, callable $callback): self
    {
        if ($this->{$fieldName}) {
            $data = $this->{$fieldName};
            $updatedData = $callback($data);
            $this->{$fieldName} = $updatedData;
        }

        return $this;
    }

    public function creator()
    {
        return $this->morphTo('creator', 'created_type', 'created_id');
    }

    public function priceSources()
    {
        return $this->belongsTo(PriceSource::class);
    }

    public function metalOrderExchanges()
    {
        return $this->hasMany(MetalOrderExchange::class);
    }
}
