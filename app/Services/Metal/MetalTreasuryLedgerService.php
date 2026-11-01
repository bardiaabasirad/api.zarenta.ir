<?php

namespace App\Services\Metal;

use App\Constants\MetalTreasuryLotStatus;
use App\Constants\MetalTreasuryMovementType;
use App\Constants\MetalTreasurySourceType;
use App\Models\MetalTreasuryLot;
use App\Models\MetalTreasuryMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

class MetalTreasuryLedgerService
{
    public const METAL_SCALE = 3;
    public const PRICE_SCALE = 0;

    public function createLot(array $attributes, ?string $idempotencyKey = null): MetalTreasuryLot
    {
        return DB::transaction(function () use ($attributes, $idempotencyKey) {
            $qty = $this->decimal($attributes['initial_qty'] ?? $attributes['qty'] ?? null, self::METAL_SCALE, 'qty');
            $unitCost = $this->decimal($attributes['unit_cost'] ?? '0', self::PRICE_SCALE, 'unit_cost');

            if (bccomp($qty, '0', self::METAL_SCALE) <= 0) {
                throw new InvalidArgumentException('مقدار ورود خزانه باید بزرگ‌تر از صفر باشد.');
            }

            if ($idempotencyKey) {
                $existing = MetalTreasuryMovement::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return MetalTreasuryLot::query()->findOrFail($existing->lot_id);
                }
            }

            $lot = MetalTreasuryLot::create([
                'metal_item_id' => $attributes['metal_item_id'],
                'maturity_code' => $attributes['maturity_code'],
                'settlement_date' => $attributes['settlement_date'] ?? null,
                'source_type' => $attributes['source_type'],
                'source_id' => $attributes['source_id'] ?? null,
                'initial_qty' => $qty,
                'remaining_qty' => $qty,
                'unit_cost' => $unitCost,
                'purchased_at' => $attributes['purchased_at'] ?? now(),
                'status' => MetalTreasuryLotStatus::OPEN,
                'meta' => $attributes['meta'] ?? null,
            ]);

            $this->recordMovement([
                'lot_id' => $lot->id,
                'movement_type' => MetalTreasuryMovementType::IN,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'metal_order_id' => $attributes['metal_order_id'] ?? null,
                'actor_id' => $attributes['actor_id'] ?? null,
                'actor_type' => $attributes['actor_type'] ?? null,
                'idempotency_key' => $idempotencyKey ?? $this->makeKey('lot-in', $lot->id),
                'reason' => $attributes['reason'] ?? 'treasury_in',
                'meta' => $attributes['movement_meta'] ?? null,
                'apply_to_remaining' => false, // remaining already set to initial
            ]);

            return $lot->fresh();
        });
    }

    /**
     * ثبت گردش و اعمال روی remaining_qty با قفل ردیف.
     */
    public function recordMovement(array $attributes): MetalTreasuryMovement
    {
        $idempotencyKey = $attributes['idempotency_key'] ?? null;

        if (!$idempotencyKey) {
            throw new InvalidArgumentException('idempotency_key برای گردش خزانه الزامی است.');
        }

        $idempotencyKey = self::normalizeIdempotencyKey($idempotencyKey);

        $existing = MetalTreasuryMovement::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($attributes, $idempotencyKey) {
            $again = MetalTreasuryMovement::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($again) {
                return $again;
            }

            $lot = MetalTreasuryLot::query()
                ->whereKey($attributes['lot_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $qty = $this->decimal($attributes['qty'], self::METAL_SCALE, 'qty');
            $type = $attributes['movement_type'];
            $apply = $attributes['apply_to_remaining'] ?? true;

            if ($apply) {
                $delta = MetalTreasuryMovementType::signedDelta($type, $qty, self::METAL_SCALE);

                // ADJUST: qty می‌تواند علامت‌دار باشد
                if ($type === MetalTreasuryMovementType::ADJUST) {
                    $delta = $this->decimal($attributes['qty'], self::METAL_SCALE, 'qty');
                    $qty = $this->absolute($delta);
                }

                $newRemaining = bcadd((string) $lot->remaining_qty, $delta, self::METAL_SCALE);

                if (bccomp($newRemaining, '0', self::METAL_SCALE) < 0) {
                    throw new LogicException('موجودی خزانه نمی‌تواند منفی شود.');
                }

                if (bccomp($newRemaining, (string) $lot->initial_qty, self::METAL_SCALE) > 0
                    && $type !== MetalTreasuryMovementType::ADJUST
                    && $type !== MetalTreasuryMovementType::IN
                    && $type !== MetalTreasuryMovementType::RELEASE
                ) {
                    // remaining نباید از initial بیشتر شود مگر اصلاح دستی
                }

                $lot->remaining_qty = $newRemaining;

                if (bccomp($newRemaining, '0', self::METAL_SCALE) === 0) {
                    $lot->status = $type === MetalTreasuryMovementType::EXTERNAL_OUT
                        ? MetalTreasuryLotStatus::CLOSED_EXTERNAL
                        : MetalTreasuryLotStatus::DEPLETED;
                } elseif ($lot->status === MetalTreasuryLotStatus::DEPLETED) {
                    $lot->status = MetalTreasuryLotStatus::OPEN;
                }

                $lot->save();
            }

            return MetalTreasuryMovement::create([
                'lot_id' => $lot->id,
                'movement_type' => $type,
                'qty' => $qty,
                'unit_cost' => isset($attributes['unit_cost'])
                    ? $this->decimal($attributes['unit_cost'], self::PRICE_SCALE, 'unit_cost')
                    : null,
                'metal_order_id' => $attributes['metal_order_id'] ?? null,
                'allocation_id' => $attributes['allocation_id'] ?? null,
                'attempt_id' => $attributes['attempt_id'] ?? null,
                'physical_delivery_request_id' => $attributes['physical_delivery_request_id'] ?? null,
                'actor_id' => $attributes['actor_id'] ?? null,
                'actor_type' => $attributes['actor_type'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'reason' => $attributes['reason'] ?? null,
                'meta' => $attributes['meta'] ?? null,
            ]);
        });
    }

    /**
     * تخصیص FIFO از Lotهای مجاز برای یک metal_item و سررسیدهای مجاز.
     *
     * @param  list<string>  $allowedMaturities  به ترتیب اولویت
     * @return list<array{lot: MetalTreasuryLot, qty: string}>
     */
    public function consumeFifo(
        int $metalItemId,
        array $allowedMaturities,
        string $neededQty,
        string $idempotencyPrefix,
        ?int $metalOrderId = null,
        ?int $actorId = null,
        ?string $actorType = null,
    ): array {
        $neededQty = $this->decimal($neededQty, self::METAL_SCALE, 'needed_qty');

        if (bccomp($neededQty, '0', self::METAL_SCALE) <= 0) {
            return [];
        }

        return DB::transaction(function () use (
            $metalItemId,
            $allowedMaturities,
            $neededQty,
            $idempotencyPrefix,
            $metalOrderId,
            $actorId,
            $actorType
        ) {
            $remainingNeed = $neededQty;
            $chunks = [];
            $seq = 0;

            foreach ($allowedMaturities as $maturity) {
                if (bccomp($remainingNeed, '0', self::METAL_SCALE) <= 0) {
                    break;
                }

                $lots = MetalTreasuryLot::query()
                    ->where('metal_item_id', $metalItemId)
                    ->where('maturity_code', $maturity)
                    ->where('status', MetalTreasuryLotStatus::OPEN)
                    ->where('remaining_qty', '>', 0)
                    ->orderBy('purchased_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($lots as $lot) {
                    if (bccomp($remainingNeed, '0', self::METAL_SCALE) <= 0) {
                        break;
                    }

                    $available = $this->decimal($lot->remaining_qty, self::METAL_SCALE, 'remaining_qty');

                    if (bccomp($available, '0', self::METAL_SCALE) <= 0) {
                        continue;
                    }

                    $take = bccomp($available, $remainingNeed, self::METAL_SCALE) >= 0
                        ? $remainingNeed
                        : $available;

                    $seq++;
                    $this->recordMovement([
                        'lot_id' => $lot->id,
                        'movement_type' => MetalTreasuryMovementType::ALLOCATE,
                        'qty' => $take,
                        'unit_cost' => $lot->unit_cost,
                        'metal_order_id' => $metalOrderId,
                        'actor_id' => $actorId,
                        'actor_type' => $actorType,
                        'idempotency_key' => self::normalizeIdempotencyKey("{$idempotencyPrefix}:fifo:{$lot->id}:{$seq}"),
                        'reason' => 'fifo_allocate',
                    ]);

                    $lot->refresh();
                    $chunks[] = ['lot' => $lot, 'qty' => $take];
                    $remainingNeed = bcsub($remainingNeed, $take, self::METAL_SCALE);
                }
            }

            return $chunks;
        });
    }

    public function availableQty(int $metalItemId, array $allowedMaturities): string
    {
        $total = '0';

        $rows = MetalTreasuryLot::query()
            ->where('metal_item_id', $metalItemId)
            ->whereIn('maturity_code', $allowedMaturities)
            ->where('status', MetalTreasuryLotStatus::OPEN)
            ->where('remaining_qty', '>', 0)
            ->get(['remaining_qty']);

        foreach ($rows as $row) {
            $total = bcadd($total, (string) $row->remaining_qty, self::METAL_SCALE);
        }

        return $total;
    }

    /**
     * خروج سنتی از باقیمانده آزاد Lot (بخش تخصیص‌نیافته)؛ بدون ثبت P&L سیستمی.
     */
    public function externalOut(
        MetalTreasuryLot $lot,
        string $qty,
        string $idempotencyKey,
        ?int $actorId = null,
        ?string $actorType = null,
        ?string $reason = 'traditional_market_sale',
        ?array $meta = null,
    ): MetalTreasuryMovement {
        if ($lot->status !== MetalTreasuryLotStatus::OPEN) {
            throw new LogicException('فقط Lotهای باز قابل خروج سنتی هستند.');
        }

        $qty = $this->decimal($qty, self::METAL_SCALE, 'qty');
        $this->assertPositiveQty($qty);

        if (bccomp($qty, (string) $lot->remaining_qty, self::METAL_SCALE) > 0) {
            throw new LogicException('مقدار خروج از باقیمانده آزاد Lot بیشتر است.');
        }

        return $this->recordMovement([
            'lot_id' => $lot->id,
            'movement_type' => MetalTreasuryMovementType::EXTERNAL_OUT,
            'qty' => $qty,
            'unit_cost' => $lot->unit_cost,
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'idempotency_key' => $idempotencyKey,
            'reason' => $reason,
            'meta' => array_merge($meta ?? [], [
                'pnl_excluded' => true,
                'external_sale' => true,
            ]),
        ]);
    }

    /**
     * خروج تحویل فیزیکی کاربر از Lot خرید حضوری آزاد؛ بدون P&L سفارش.
     */
    public function physicalDeliveryOut(
        MetalTreasuryLot $lot,
        string $qty,
        string $idempotencyKey,
        int $physicalDeliveryRequestId,
        ?int $actorId = null,
        ?string $actorType = null,
        ?array $meta = null,
    ): MetalTreasuryMovement {
        if ($lot->status !== MetalTreasuryLotStatus::OPEN) {
            throw new LogicException('فقط Lotهای باز برای تحویل فیزیکی قابل استفاده هستند.');
        }

        if ($lot->source_type !== MetalTreasurySourceType::PHYSICAL_PURCHASE) {
            throw new LogicException('تحویل از خزانه فقط از Lotهای خرید حضوری مجاز است.');
        }

        $qty = $this->decimal($qty, self::METAL_SCALE, 'qty');
        $this->assertPositiveQty($qty);

        if (bccomp($qty, (string) $lot->remaining_qty, self::METAL_SCALE) > 0) {
            throw new LogicException('وزن آزاد Lot برای تحویل فیزیکی کافی نیست.');
        }

        return $this->recordMovement([
            'lot_id' => $lot->id,
            'movement_type' => MetalTreasuryMovementType::PHYSICAL_DELIVERY_OUT,
            'qty' => $qty,
            'unit_cost' => $lot->unit_cost,
            'physical_delivery_request_id' => $physicalDeliveryRequestId,
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'idempotency_key' => $idempotencyKey,
            'reason' => 'physical_delivery_out',
            'meta' => array_merge($meta ?? [], [
                'pnl_excluded' => true,
                'physical_delivery' => true,
                'physical_delivery_request_id' => $physicalDeliveryRequestId,
            ]),
        ]);
    }

    public function releaseAllocationQty(
        MetalTreasuryLot $lot,
        string $qty,
        int $allocationId,
        string $idempotencyKey,
        ?int $metalOrderId = null,
        ?int $actorId = null,
        ?string $actorType = null,
        ?string $reason = 'allocation_reverse',
    ): MetalTreasuryMovement {
        return $this->recordMovement([
            'lot_id' => $lot->id,
            'movement_type' => MetalTreasuryMovementType::RELEASE,
            'qty' => $qty,
            'unit_cost' => $lot->unit_cost,
            'metal_order_id' => $metalOrderId,
            'allocation_id' => $allocationId,
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'idempotency_key' => $idempotencyKey,
            'reason' => $reason,
        ]);
    }

    public function decimal(mixed $value, int $scale, string $field): string
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException("مقدار {$field} نامعتبر است.");
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException("مقدار {$field} عددی نیست.");
        }

        return bcadd((string) $value, '0', $scale);
    }

    public function absolute(string $value): string
    {
        return bccomp($value, '0', self::METAL_SCALE) < 0
            ? bcmul($value, '-1', self::METAL_SCALE)
            : bcadd($value, '0', self::METAL_SCALE);
    }

    public function makeKey(string $prefix, int|string $id): string
    {
        return self::normalizeIdempotencyKey($prefix . ':' . $id . ':' . uniqid('', true));
    }

    /**
     * کلیدهای طولانی (مثلاً با UUID) را به طول امن ستون فشرده می‌کند.
     */
    public static function normalizeIdempotencyKey(string $key, int $max = 128): string
    {
        if (strlen($key) <= $max) {
            return $key;
        }

        return 'h:' . hash('sha256', $key);
    }

    public function assertPositiveQty(string $qty, string $field = 'qty'): void
    {
        if (bccomp($qty, '0', self::METAL_SCALE) <= 0) {
            throw new InvalidArgumentException("{$field} باید بزرگ‌تر از صفر باشد.");
        }
    }

    public function minQty(string $a, string $b): string
    {
        return bccomp($a, $b, self::METAL_SCALE) <= 0 ? $a : $b;
    }
}
