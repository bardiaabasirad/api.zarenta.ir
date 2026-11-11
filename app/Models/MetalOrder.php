<?php

namespace App\Models;

use App\Observers\MetalOrderObserver;
use App\Services\MetalPriceService;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([MetalOrderObserver::class])]
class MetalOrder extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'extra_data' => 'array',
        'settlement_date' => 'date:Y-m-d',
        'coverage_deadline' => 'date:Y-m-d',
        'last_coverage_attempt_at' => 'datetime',
        'qty' => 'float',
        'fee' => 'float',
        'amount' => 'float',
        'covered_qty' => 'string',
        'open_qty' => 'string',
    ];

    protected $appends = ['product'];

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

    /**
     * Virtual product payload for API/UI backward compatibility.
     * fee is already final (fee_margin applied at order creation).
     */
    public function product(): Attribute
    {
        return Attribute::make(
            get: function () {
                $extra = $this->extra_data ?? [];
                $unitName = Unit::cached((string) ($this->unit ?? ''))?->name;
                $targetUnitName = Unit::cached((string) ($this->target_unit_id ?? ''))?->name;
                $displayMode = $this->target_unit_id
                    ? app(MetalPriceService::class)->toLegacyDisplayMode((string) $this->target_unit_id)
                    : null;

                $data = [
                    'name' => $this->product_name,
                    'metal_item_id' => $this->metal_item_id,
                    'fee' => $this->fee,
                    'display_fee' => $extra['display_fee'] ?? $this->fee,
                    'unit' => $unitName ?? $this->unit,
                    'en_unit' => $this->unit,
                    'quantity' => $this->qty,
                    'pricing_unit_id' => $this->pricing_unit_id,
                    'target_unit_id' => $this->target_unit_id,
                    'target_unit_name' => $targetUnitName,
                    'display_mode' => $displayMode,
                    'amount' => $this->amount,
                ];

                if (isset($extra['new_fee'])) {
                    $data['new_fee'] = $extra['new_fee'];
                }

                if (isset($extra['new_display_fee'])) {
                    $data['new_display_fee'] = $extra['new_display_fee'];
                }

                return $data;
            },
        );
    }

    /**
     * Apply fee_margin onto fee once, at order registration time.
     */
    public static function finalizeFee(
        float|int|string $fee,
        float|int|string $feeMargin = 0,
        ?string $toleranceType = 'fixed_amount'
    ): float {
        $fee = (float) $fee;
        $feeMargin = (float) $feeMargin;

        if ($toleranceType === 'percentage') {
            return $fee + ($fee * $feeMargin / 100);
        }

        return $fee + $feeMargin;
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, ['succeed', 'rejected']);
    }

    public function metalItem()
    {
        return $this->belongsTo(MetalItem::class, 'metal_item_id');
    }

    public function pricingUnit()
    {
        return $this->belongsTo(Unit::class, 'pricing_unit_id');
    }

    public function targetUnit()
    {
        return $this->belongsTo(Unit::class, 'target_unit_id');
    }

    public function leverageCheck()
    {
        return $this->hasOne(MetalOrderLeverageCheck::class);
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

    public function coverageAllocations()
    {
        return $this->hasMany(MetalOrderCoverageAllocation::class);
    }

    public function activeCoverageAllocations()
    {
        return $this->hasMany(MetalOrderCoverageAllocation::class)->whereNull('reversed_at');
    }

    public function coverageAttempts()
    {
        return $this->hasMany(MetalOrderCoverageAttempt::class);
    }

    public function settlementCases()
    {
        return $this->belongsToMany(
            MetalSettlementCase::class,
            'metal_settlement_case_order',
            'metal_order_id',
            'metal_settlement_case_id'
        )->withTimestamps();
    }
}
