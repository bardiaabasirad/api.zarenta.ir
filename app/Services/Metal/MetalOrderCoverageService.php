<?php

namespace App\Services\Metal;

use App\Constants\MetalCoverageStatus;
use App\Constants\MetalTreasurySourceType;
use App\Models\MetalOrder;
use App\Models\MetalOrderCoverageAllocation;
use App\Models\MetalTreasuryLot;
use App\Models\MetalTreasuryPhysicalPurchase;
use App\Services\MetalOrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

class MetalOrderCoverageService
{
    public function __construct(
        private readonly MetalTreasuryLedgerService $ledger,
        private readonly MetalMaturityService $maturity,
        private readonly MetalPartnerCoverageService $partner,
    ) {
    }

    /**
     * پوشش خودکار خرید: اول خزانه، سپس مابقی کسری از همکار
     * (فقط اگر ثبت سفارش خودکار همکار برای آن فلز باز باشد).
     * شکست همکار سفارش را reject نمی‌کند؛ کسری باز می‌ماند.
     */
    public function coverBuyOrder(MetalOrder $order, ?string $idempotencyKey = null, bool $tryPartner = true): MetalOrder
    {
        if ($order->order_type !== 'buy') {
            return $order;
        }

        $idempotencyKey = $idempotencyKey ?: 'auto-cover:' . $order->id;

        $result = DB::transaction(function () use ($order, $idempotencyKey) {
            $order = MetalOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->initializeCoverageFields($order);

            if ($order->coverage_status === MetalCoverageStatus::FULLY_COVERED
                || bccomp((string) $order->open_qty, '0', MetalTreasuryLedgerService::METAL_SCALE) <= 0
            ) {
                return ['order' => $order->fresh(), 'open_before_partner' => '0', 'treasury_covered' => '0'];
            }

            $order = $this->maturity->ensureOrderSettlement($order);
            $orderMaturity = $this->maturity->codeForOrder($order);
            $allowed = $this->maturity->allowedSourceMaturities($orderMaturity);
            $sourceMetalIds = $this->maturity->allowedSourceMetalItemIds($order);
            $openQty = $this->ledger->decimal($order->open_qty, MetalTreasuryLedgerService::METAL_SCALE, 'open_qty');

            $available = $this->ledger->availableQty($sourceMetalIds, $allowed);
            $fromTreasury = $this->ledger->minQty($openQty, $available);

            $treasuryCovered = '0';

            if (bccomp($fromTreasury, '0', MetalTreasuryLedgerService::METAL_SCALE) > 0) {
                $chunks = $this->ledger->consumeFifo(
                    metalItemId: $sourceMetalIds,
                    allowedMaturities: $allowed,
                    neededQty: $fromTreasury,
                    idempotencyPrefix: $idempotencyKey . ':treasury',
                    metalOrderId: $order->id,
                    actorId: Auth::id(),
                    actorType: $this->actorType(),
                );

                foreach ($chunks as $chunk) {
                    $this->createAllocationFromLot(
                        order: $order,
                        lot: $chunk['lot'],
                        qty: $chunk['qty'],
                        sourceType: MetalTreasurySourceType::TREASURY,
                        idempotencyKey: $idempotencyKey . ':alloc:lot:' . $chunk['lot']->id,
                    );
                    $treasuryCovered = bcadd($treasuryCovered, $chunk['qty'], MetalTreasuryLedgerService::METAL_SCALE);
                }
            }

            $order = $this->refreshCoverageCaches($order->fresh());

            return [
                'order' => $order,
                'open_before_partner' => (string) $order->open_qty,
                'treasury_covered' => $treasuryCovered,
            ];
        });

        $order = $result['order'];

        // مابقی کسری پس از خزانه → همکار (اگر ثبت خودکار باز باشد)
        if (
            $tryPartner
            && bccomp((string) $order->open_qty, '0', MetalTreasuryLedgerService::METAL_SCALE) > 0
            && $this->isPartnerAutoOrderOpen($order)
        ) {
            $order = $this->partner->coverOpenQtyFromPartner(
                $order,
                (string) $order->open_qty,
                $idempotencyKey . ':partner'
            );
        }

        $order = $this->refreshCoverageCaches($order->fresh());
        $this->finalizeIfFullyCovered($order);

        return $order->fresh();
    }

    /**
     * آیا ثبت سفارش خودکار همکار (سراسری + تنظیم فلز) باز است؟
     */
    public function isPartnerAutoOrderOpen(MetalOrder $order): bool
    {
        try {
            $globalEnabled = \App\Models\Setting::query()
                ->where('option_key', 'auto_order_dispatch_enabled')
                ->value('option_value');

            if (! $globalEnabled) {
                return false;
            }

            $hamtala = app(\App\Services\Hamtala\HamtalaOrderExchangeService::class);

            return ! $hamtala->isAutoOrderDisabled($order);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function allocateFromTreasury(
        MetalOrder $order,
        string $qty,
        ?string $idempotencyKey = null,
        ?int $lotId = null,
    ): MetalOrder {
        $idempotencyKey = MetalTreasuryLedgerService::normalizeIdempotencyKey(
            $idempotencyKey ?: ('at:' . $order->id . ':' . Str::lower(Str::random(12)))
        );

        return DB::transaction(function () use ($order, $qty, $idempotencyKey, $lotId) {
            $order = MetalOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->initializeCoverageFields($order);
            $this->assertBuyOpen($order);

            $qty = $this->ledger->decimal($qty, MetalTreasuryLedgerService::METAL_SCALE, 'qty');
            $this->ledger->assertPositiveQty($qty);
            $this->assertNotOverOpen($order, $qty);

            $orderMaturity = $this->maturity->codeForOrder($order);
            $allowed = $this->maturity->allowedSourceMaturities($orderMaturity);
            $sourceMetalIds = $this->maturity->allowedSourceMetalItemIds($order);

            if ($lotId) {
                $lot = MetalTreasuryLot::query()->whereKey($lotId)->lockForUpdate()->firstOrFail();
                $this->assertLotCompatible($order, $lot, $allowed);
                $take = $this->ledger->minQty($qty, (string) $lot->remaining_qty);

                $this->ledger->recordMovement([
                    'lot_id' => $lot->id,
                    'movement_type' => \App\Constants\MetalTreasuryMovementType::ALLOCATE,
                    'qty' => $take,
                    'unit_cost' => $lot->unit_cost,
                    'metal_order_id' => $order->id,
                    'actor_id' => Auth::id(),
                    'actor_type' => $this->actorType(),
                    'idempotency_key' => MetalTreasuryLedgerService::normalizeIdempotencyKey($idempotencyKey . ':mov'),
                    'reason' => 'admin_treasury_allocate',
                ]);

                $this->createAllocationFromLot(
                    order: $order,
                    lot: $lot->fresh(),
                    qty: $take,
                    sourceType: MetalTreasurySourceType::TREASURY,
                    idempotencyKey: MetalTreasuryLedgerService::normalizeIdempotencyKey($idempotencyKey . ':alloc'),
                );
            } else {
                $chunks = $this->ledger->consumeFifo(
                    metalItemId: $sourceMetalIds,
                    allowedMaturities: $allowed,
                    neededQty: $qty,
                    idempotencyPrefix: $idempotencyKey,
                    metalOrderId: $order->id,
                    actorId: Auth::id(),
                    actorType: $this->actorType(),
                );

                $taken = '0';
                foreach ($chunks as $chunk) {
                    $this->createAllocationFromLot(
                        order: $order,
                        lot: $chunk['lot'],
                        qty: $chunk['qty'],
                        sourceType: MetalTreasurySourceType::TREASURY,
                        idempotencyKey: MetalTreasuryLedgerService::normalizeIdempotencyKey(
                            $idempotencyKey . ':alloc:lot:' . $chunk['lot']->id
                        ),
                    );
                    $taken = bcadd($taken, $chunk['qty'], MetalTreasuryLedgerService::METAL_SCALE);
                }

                if (bccomp($taken, $qty, MetalTreasuryLedgerService::METAL_SCALE) < 0) {
                    throw new LogicException('موجودی مجاز خزانه برای تخصیص کافی نیست.');
                }
            }

            $order = $this->refreshCoverageCaches($order->fresh());
            $this->finalizeIfFullyCovered($order);

            return $order->fresh();
        });
    }

    public function allocateFromUserSellLot(
        MetalOrder $order,
        int $lotId,
        string $qty,
        ?string $idempotencyKey = null,
        string $reason = 'admin_user_sell_allocate',
    ): MetalOrder {
        $idempotencyKey = MetalTreasuryLedgerService::normalizeIdempotencyKey(
            $idempotencyKey ?: ('aus:' . $order->id . ':' . $lotId . ':' . Str::lower(Str::random(12)))
        );

        return DB::transaction(function () use ($order, $lotId, $qty, $idempotencyKey, $reason) {
            $order = MetalOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->initializeCoverageFields($order);
            $this->assertBuyOpen($order);

            $qty = $this->ledger->decimal($qty, MetalTreasuryLedgerService::METAL_SCALE, 'qty');
            $this->assertNotOverOpen($order, $qty);

            $lot = MetalTreasuryLot::query()->whereKey($lotId)->lockForUpdate()->firstOrFail();

            if ($lot->source_type !== MetalTreasurySourceType::USER_WALLET_SELL) {
                throw new InvalidArgumentException('Lot انتخاب‌شده فروش کاربر نیست.');
            }

            $orderMaturity = $this->maturity->codeForOrder($order);
            $allowed = $this->maturity->allowedSourceMaturities($orderMaturity);
            $this->assertLotCompatible($order, $lot, $allowed);

            $take = $this->ledger->minQty($qty, (string) $lot->remaining_qty);
            $this->ledger->assertPositiveQty($take);

            $this->ledger->recordMovement([
                'lot_id' => $lot->id,
                'movement_type' => \App\Constants\MetalTreasuryMovementType::ALLOCATE,
                'qty' => $take,
                'unit_cost' => $lot->unit_cost,
                'metal_order_id' => $order->id,
                'actor_id' => Auth::id(),
                'actor_type' => $this->actorType(),
                'idempotency_key' => MetalTreasuryLedgerService::normalizeIdempotencyKey($idempotencyKey . ':mov'),
                'reason' => $reason,
            ]);

            $this->createAllocationFromLot(
                order: $order,
                lot: $lot->fresh(),
                qty: $take,
                sourceType: MetalTreasurySourceType::USER_WALLET_SELL,
                idempotencyKey: MetalTreasuryLedgerService::normalizeIdempotencyKey($idempotencyKey . ':alloc'),
                sourceId: $lot->source_id,
            );

            $order = $this->refreshCoverageCaches($order->fresh());
            $this->finalizeIfFullyCovered($order);

            return $order->fresh();
        });
    }

    /**
     * کسری خریدهای باز سازگار با این لات فروش را FIFO پوشش بده.
     */
    public function distributeUserSellLotToOpenBuys(MetalTreasuryLot $lot): MetalTreasuryLot
    {
        if ($lot->source_type !== MetalTreasurySourceType::USER_WALLET_SELL) {
            return $lot;
        }

        if (bccomp((string) $lot->remaining_qty, '0', MetalTreasuryLedgerService::METAL_SCALE) <= 0) {
            return $lot;
        }

        $lotMetalItemId = (int) $lot->metal_item_id;
        $lotMaturity = (string) $lot->maturity_code;

        foreach ($this->openBuyCandidatesForSellSource($lotMetalItemId, $lotMaturity) as $order) {
            $lot = $lot->fresh();

            if (!$lot || bccomp((string) $lot->remaining_qty, '0', MetalTreasuryLedgerService::METAL_SCALE) <= 0) {
                break;
            }

            if (!$this->maturity->isSourceMetalItemAllowed($order, $lotMetalItemId)) {
                continue;
            }

            if (!$this->maturity->isSourceAllowed($this->maturity->codeForOrder($order), $lotMaturity)) {
                continue;
            }

            $take = $this->ledger->minQty((string) $order->open_qty, (string) $lot->remaining_qty);

            if (bccomp($take, '0', MetalTreasuryLedgerService::METAL_SCALE) <= 0) {
                continue;
            }

            $this->allocateFromUserSellLot(
                order: $order,
                lotId: (int) $lot->id,
                qty: $take,
                idempotencyKey: 'auto-user-sell:' . ($lot->source_id ?? $lot->id) . ':buy:' . $order->id,
                reason: 'auto_user_sell_allocate',
            );
        }

        return $lot->fresh() ?? $lot;
    }

    /**
     * فروش کاربر → اول تطبیق با خریدهای باز (فقط مقدار تطبیق‌شده وارد خزانه می‌شود).
     *
     * @return array{matched_qty: string, remainder_qty: string, lot: ?MetalTreasuryLot}
     */
    public function matchUserSellToOpenBuys(MetalOrder $sellOrder): array
    {
        if ($sellOrder->order_type !== 'sell') {
            return ['matched_qty' => '0.000', 'remainder_qty' => '0.000', 'lot' => null];
        }

        $sellOrder = $this->maturity->ensureOrderSettlement($sellOrder);
        $sellQty = $this->ledger->decimal($sellOrder->qty, MetalTreasuryLedgerService::METAL_SCALE, 'qty');
        $maturity = $this->maturity->codeForOrder($sellOrder);
        $key = 'user-sell-in:' . $sellOrder->id;

        $existing = \App\Models\MetalTreasuryMovement::query()
            ->where('idempotency_key', $key)
            ->first();

        if ($existing) {
            $lot = MetalTreasuryLot::query()->find($existing->lot_id);
            if ($lot) {
                $lot = $this->distributeUserSellLotToOpenBuys($lot);
            }

            $matched = $this->ledger->decimal($lot?->initial_qty ?? '0', MetalTreasuryLedgerService::METAL_SCALE, 'matched');
            $remainder = bcsub($sellQty, $matched, MetalTreasuryLedgerService::METAL_SCALE);
            if (bccomp($remainder, '0', MetalTreasuryLedgerService::METAL_SCALE) < 0) {
                $remainder = '0.000';
            }

            $this->persistSellMatchMeta($sellOrder, $matched, $remainder);

            return ['matched_qty' => $matched, 'remainder_qty' => $remainder, 'lot' => $lot];
        }

        $openDemand = $this->compatibleOpenBuyDemand(
            (int) $sellOrder->metal_item_id,
            $maturity
        );
        $matched = $this->ledger->minQty($sellQty, $openDemand);
        $remainder = bcsub($sellQty, $matched, MetalTreasuryLedgerService::METAL_SCALE);

        $lot = null;
        if (bccomp($matched, '0', MetalTreasuryLedgerService::METAL_SCALE) > 0) {
            $lot = $this->ledger->createLot([
                'metal_item_id' => $sellOrder->metal_item_id,
                'maturity_code' => $maturity,
                'settlement_date' => $sellOrder->settlement_date,
                'source_type' => MetalTreasurySourceType::USER_WALLET_SELL,
                'source_id' => $sellOrder->id,
                'initial_qty' => $matched,
                'unit_cost' => (string) (int) round((float) $sellOrder->fee),
                'purchased_at' => $sellOrder->updated_at ?? now(),
                'metal_order_id' => $sellOrder->id,
                'reason' => 'user_wallet_sell_match',
                'meta' => [
                    'sell_order_tracking_code' => $sellOrder->tracking_code,
                    'matched_only' => true,
                ],
            ], $key);

            $lot = $this->distributeUserSellLotToOpenBuys($lot);
        }

        $this->persistSellMatchMeta($sellOrder, $matched, $remainder);

        return ['matched_qty' => $matched, 'remainder_qty' => $remainder, 'lot' => $lot];
    }

    public function registerPhysicalPurchase(array $data): MetalTreasuryPhysicalPurchase
    {
        return DB::transaction(function () use ($data) {
            $qty = $this->ledger->decimal($data['qty'], MetalTreasuryLedgerService::METAL_SCALE, 'qty');
            $unitCost = $this->ledger->decimal($data['unit_cost'], MetalTreasuryLedgerService::PRICE_SCALE, 'unit_cost');
            $this->ledger->assertPositiveQty($qty);

            $metalItem = \App\Models\MetalItem::withoutGlobalScope('visible')
                ->findOrFail($data['metal_item_id']);

            // سررسید از settlement_working_days فلز گرفته می‌شود (0→T0، 1→T1، ≥2→T2)
            $maturityCode = $this->maturity->codeForMetalItem($metalItem);
            $settlementDate = $this->maturity->settlementDateForItem($metalItem)->toDateString();

            $purchase = MetalTreasuryPhysicalPurchase::create([
                'metal_item_id' => $metalItem->id,
                'maturity_code' => $maturityCode,
                'settlement_date' => $settlementDate,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'document_number' => $data['document_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_id' => Auth::id(),
                'created_by_type' => $this->actorType(),
                'meta' => $data['meta'] ?? null,
            ]);

            $idem = $data['idempotency_key'] ?? ('physical:' . $purchase->id);

            $this->ledger->createLot([
                'metal_item_id' => $purchase->metal_item_id,
                'maturity_code' => $purchase->maturity_code,
                'settlement_date' => $purchase->settlement_date,
                'source_type' => MetalTreasurySourceType::PHYSICAL_PURCHASE,
                'source_id' => $purchase->id,
                'initial_qty' => $qty,
                'unit_cost' => $unitCost,
                'purchased_at' => now(),
                'actor_id' => Auth::id(),
                'actor_type' => $this->actorType(),
                'reason' => 'physical_purchase',
                'meta' => [
                    'document_number' => $purchase->document_number,
                    'available_for_hedging' => true,
                ],
            ], $idem);

            return $purchase->fresh('lot');
        });
    }

    public function allocatePhysicalToOrder(
        MetalOrder $order,
        int $lotId,
        string $qty,
        ?string $idempotencyKey = null,
    ): MetalOrder {
        $idempotencyKey = MetalTreasuryLedgerService::normalizeIdempotencyKey(
            $idempotencyKey ?: ('ap:' . $order->id . ':' . $lotId . ':' . Str::lower(Str::random(12)))
        );

        return DB::transaction(function () use ($order, $lotId, $qty, $idempotencyKey) {
            $order = MetalOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->initializeCoverageFields($order);
            $this->assertBuyOpen($order);

            $qty = $this->ledger->decimal($qty, MetalTreasuryLedgerService::METAL_SCALE, 'qty');
            $this->assertNotOverOpen($order, $qty);

            $lot = MetalTreasuryLot::query()->whereKey($lotId)->lockForUpdate()->firstOrFail();

            if ($lot->source_type !== MetalTreasurySourceType::PHYSICAL_PURCHASE) {
                throw new InvalidArgumentException('Lot خرید حضوری نیست.');
            }

            $orderMaturity = $this->maturity->codeForOrder($order);
            $allowed = $this->maturity->allowedSourceMaturities($orderMaturity);
            $this->assertLotCompatible($order, $lot, $allowed);

            $take = $this->ledger->minQty($qty, (string) $lot->remaining_qty);

            $this->ledger->recordMovement([
                'lot_id' => $lot->id,
                'movement_type' => \App\Constants\MetalTreasuryMovementType::ALLOCATE,
                'qty' => $take,
                'unit_cost' => $lot->unit_cost,
                'metal_order_id' => $order->id,
                'actor_id' => Auth::id(),
                'actor_type' => $this->actorType(),
                'idempotency_key' => MetalTreasuryLedgerService::normalizeIdempotencyKey($idempotencyKey . ':mov'),
                'reason' => 'admin_physical_allocate',
            ]);

            $this->createAllocationFromLot(
                order: $order,
                lot: $lot->fresh(),
                qty: $take,
                sourceType: MetalTreasurySourceType::PHYSICAL_PURCHASE,
                idempotencyKey: MetalTreasuryLedgerService::normalizeIdempotencyKey($idempotencyKey . ':alloc'),
                sourceId: $lot->source_id,
            );

            $order = $this->refreshCoverageCaches($order->fresh());
            $this->finalizeIfFullyCovered($order);

            return $order->fresh();
        });
    }

    /**
     * خروج سنتی از لات آزاد.
     * برای لات فروش کاربر، نرخ فروش سنتی الزامی است و P&L روی سفارش فروش کاربر ثبت می‌شود.
     *
     * @return array{lot: MetalTreasuryLot, allocation: ?MetalOrderCoverageAllocation, realized_pnl: ?string}
     */
    public function externalOutFromPhysicalLot(
        int $lotId,
        string $qty,
        ?string $idempotencyKey = null,
        ?string $notes = null,
        ?string $saleUnitPrice = null,
    ): array {
        $idempotencyKey = MetalTreasuryLedgerService::normalizeIdempotencyKey(
            $idempotencyKey ?: ('eo:' . $lotId . ':' . Str::lower(Str::random(12)))
        );

        return DB::transaction(function () use ($lotId, $qty, $idempotencyKey, $notes, $saleUnitPrice) {
            $lot = MetalTreasuryLot::query()->whereKey($lotId)->lockForUpdate()->firstOrFail();
            $qty = $this->ledger->decimal($qty, MetalTreasuryLedgerService::METAL_SCALE, 'qty');
            $this->ledger->assertPositiveQty($qty);

            $isUserSell = $lot->source_type === MetalTreasurySourceType::USER_WALLET_SELL;
            $allocation = null;
            $realizedPnl = null;
            $sellOrderId = null;

            $meta = ['notes' => $notes];

            if ($isUserSell) {
                if ($saleUnitPrice === null || $saleUnitPrice === '') {
                    throw new InvalidArgumentException('برای خروج سنتی فروش کاربر، نرخ فروش الزامی است.');
                }

                if (!$lot->source_id) {
                    throw new LogicException('لات فروش کاربر به سفارش فروش متصل نیست.');
                }

                $sellOrder = MetalOrder::query()->whereKey($lot->source_id)->lockForUpdate()->firstOrFail();

                if ($sellOrder->order_type !== 'sell') {
                    throw new LogicException('منبع لات فروش کاربر معتبر نیست.');
                }

                $sale = $this->ledger->decimal($saleUnitPrice, MetalTreasuryLedgerService::PRICE_SCALE, 'sale_unit_price');
                $cost = $this->ledger->decimal($lot->unit_cost, MetalTreasuryLedgerService::PRICE_SCALE, 'unit_cost');
                $realizedPnl = bcmul(
                    $qty,
                    bcsub($sale, $cost, MetalTreasuryLedgerService::PRICE_SCALE),
                    MetalTreasuryLedgerService::PRICE_SCALE
                );
                $sellOrderId = $sellOrder->id;

                $allocation = $this->createTraditionalExitAllocation(
                    sellOrder: $sellOrder,
                    lot: $lot,
                    qty: $qty,
                    saleUnitPrice: $sale,
                    costUnitPrice: $cost,
                    realizedPnl: $realizedPnl,
                    idempotencyKey: MetalTreasuryLedgerService::normalizeIdempotencyKey($idempotencyKey . ':alloc'),
                );

                $meta = array_merge($meta, [
                    'pnl_excluded' => false,
                    'sale_unit_price' => $sale,
                    'user_unit_price' => $cost,
                    'realized_pnl' => $realizedPnl,
                    'sell_order_id' => $sellOrderId,
                ]);
            }

            $this->ledger->externalOut(
                lot: $lot,
                qty: $qty,
                idempotencyKey: $idempotencyKey,
                actorId: Auth::id(),
                actorType: $this->actorType(),
                reason: 'traditional_market_sale',
                meta: $meta,
                metalOrderId: $sellOrderId,
                allocationId: $allocation?->id,
            );

            return [
                'lot' => $lot->fresh(),
                'allocation' => $allocation,
                'realized_pnl' => $realizedPnl,
            ];
        });
    }

    public function createTraditionalExitAllocation(
        MetalOrder $sellOrder,
        MetalTreasuryLot $lot,
        string $qty,
        string $saleUnitPrice,
        string $costUnitPrice,
        string $realizedPnl,
        string $idempotencyKey,
    ): MetalOrderCoverageAllocation {
        $idempotencyKey = MetalTreasuryLedgerService::normalizeIdempotencyKey($idempotencyKey);

        $existing = MetalOrderCoverageAllocation::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        return MetalOrderCoverageAllocation::create([
            'metal_order_id' => $sellOrder->id,
            'metal_item_id' => $sellOrder->metal_item_id,
            'source_type' => MetalTreasurySourceType::TRADITIONAL_EXIT,
            'source_id' => $lot->id,
            'lot_id' => $lot->id,
            'attempt_id' => null,
            'maturity_code' => $lot->maturity_code,
            'qty' => $this->ledger->decimal($qty, MetalTreasuryLedgerService::METAL_SCALE, 'qty'),
            'customer_unit_price' => $saleUnitPrice,
            'coverage_unit_cost' => $costUnitPrice,
            'realized_pnl' => $realizedPnl,
            'allocated_at' => now(),
            'allocated_by_id' => Auth::id(),
            'allocated_by_type' => $this->actorType(),
            'idempotency_key' => $idempotencyKey,
            'meta' => [
                'kind' => 'traditional_exit',
                'sale_unit_price' => $saleUnitPrice,
                'user_unit_price' => $costUnitPrice,
            ],
        ]);
    }

    /**
     * پس از succeed فروش:
     * - مقدار تطبیق‌شده با خریدهای باز (و هر باقیمانده‌ای که به همکار نرفته) وارد خزانه می‌شود
     * - مقدار فروخته‌شده به همکار (partner_sell_qty) وارد خزانه نمی‌شود
     */
    public function ingestUserSellOrder(MetalOrder $sellOrder): ?MetalTreasuryLot
    {
        if ($sellOrder->order_type !== 'sell' || $sellOrder->status !== 'succeed') {
            return null;
        }

        $match = $this->matchUserSellToOpenBuys($sellOrder);
        $sellQty = $this->ledger->decimal($sellOrder->qty, MetalTreasuryLedgerService::METAL_SCALE, 'qty');
        $partnerQty = $this->ledger->decimal(
            (string) (($sellOrder->extra_data['partner_sell_qty'] ?? '0')),
            MetalTreasuryLedgerService::METAL_SCALE,
            'partner_sell_qty'
        );
        $keepQty = bcsub($sellQty, $partnerQty, MetalTreasuryLedgerService::METAL_SCALE);
        if (bccomp($keepQty, '0', MetalTreasuryLedgerService::METAL_SCALE) < 0) {
            $keepQty = '0.000';
        }

        $matched = $match['matched_qty'];
        $remainderKeep = bcsub($keepQty, $matched, MetalTreasuryLedgerService::METAL_SCALE);
        if (bccomp($remainderKeep, '0', MetalTreasuryLedgerService::METAL_SCALE) < 0) {
            $remainderKeep = '0.000';
        }

        $lot = $match['lot'];

        // باقیمانده‌ای که به همکار نرفت → خزانه (مثلاً همکار بسته/ناموفق یا تأیید دستی)
        if (bccomp($remainderKeep, '0', MetalTreasuryLedgerService::METAL_SCALE) > 0) {
            $sellOrder = $this->maturity->ensureOrderSettlement($sellOrder);
            $remainderLot = $this->ledger->createLot([
                'metal_item_id' => $sellOrder->metal_item_id,
                'maturity_code' => $this->maturity->codeForOrder($sellOrder),
                'settlement_date' => $sellOrder->settlement_date,
                'source_type' => MetalTreasurySourceType::USER_WALLET_SELL,
                'source_id' => $sellOrder->id,
                'initial_qty' => $remainderKeep,
                'unit_cost' => (string) (int) round((float) $sellOrder->fee),
                'purchased_at' => $sellOrder->updated_at ?? now(),
                'metal_order_id' => $sellOrder->id,
                'reason' => 'user_wallet_sell_remainder',
                'meta' => [
                    'sell_order_tracking_code' => $sellOrder->tracking_code,
                    'remainder_kept' => true,
                ],
            ], 'user-sell-remainder:' . $sellOrder->id);

            $remainderLot = $this->distributeUserSellLotToOpenBuys($remainderLot);
            $lot = $lot ?? $remainderLot;
        }

        return $lot;
    }

    /**
     * @return \Illuminate\Support\Collection<int, MetalOrder>
     */
    private function openBuyCandidatesForSellSource(int $lotMetalItemId, string $lotMaturity)
    {
        return MetalOrder::query()
            ->with('metalItem')
            ->where('order_type', 'buy')
            ->where('status', '!=', 'rejected')
            ->whereIn('coverage_status', MetalCoverageStatus::openStatuses())
            ->where('open_qty', '>', 0)
            ->where(function ($q) use ($lotMetalItemId) {
                $q->where('metal_item_id', $lotMetalItemId)
                    ->orWhereIn('metal_item_id', function ($sub) use ($lotMetalItemId) {
                        $sub->select('id')
                            ->from('metal_items')
                            ->where('settlement_metal_item_id', $lotMetalItemId);
                    });
            })
            ->orderByRaw('CASE WHEN coverage_deadline IS NULL THEN 1 ELSE 0 END')
            ->orderBy('coverage_deadline')
            ->orderBy('id')
            ->get()
            ->filter(function (MetalOrder $order) use ($lotMetalItemId, $lotMaturity) {
                return $this->maturity->isSourceMetalItemAllowed($order, $lotMetalItemId)
                    && $this->maturity->isSourceAllowed($this->maturity->codeForOrder($order), $lotMaturity);
            })
            ->values();
    }

    private function compatibleOpenBuyDemand(int $metalItemId, string $maturity): string
    {
        $demand = '0.000';

        foreach ($this->openBuyCandidatesForSellSource($metalItemId, $maturity) as $order) {
            $demand = bcadd(
                $demand,
                $this->ledger->decimal($order->open_qty, MetalTreasuryLedgerService::METAL_SCALE, 'open_qty'),
                MetalTreasuryLedgerService::METAL_SCALE
            );
        }

        return $demand;
    }

    private function persistSellMatchMeta(MetalOrder $sellOrder, string $matched, string $remainder): void
    {
        $extra = $sellOrder->extra_data ?? [];
        $extra['matched_to_buys_qty'] = $matched;
        $extra['sell_remainder_qty'] = $remainder;
        $sellOrder->extra_data = $extra;
        $sellOrder->save();
    }

    public function reverseAllocation(
        MetalOrderCoverageAllocation $allocation,
        ?string $reason = 'coverage_reverse',
    ): MetalOrderCoverageAllocation {
        return DB::transaction(function () use ($allocation, $reason) {
            $allocation = MetalOrderCoverageAllocation::query()
                ->whereKey($allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($allocation->reversed_at !== null) {
                return $allocation;
            }

            if ($allocation->lot_id) {
                $lot = MetalTreasuryLot::query()->whereKey($allocation->lot_id)->lockForUpdate()->firstOrFail();
                $movement = $this->ledger->releaseAllocationQty(
                    lot: $lot,
                    qty: (string) $allocation->qty,
                    allocationId: $allocation->id,
                    idempotencyKey: 'reverse-alloc:' . $allocation->id,
                    metalOrderId: $allocation->metal_order_id,
                    actorId: Auth::id(),
                    actorType: $this->actorType(),
                    reason: $reason,
                );
                $allocation->reversal_movement_id = $movement->id;
            }

            $allocation->reversed_at = now();
            $allocation->save();

            $this->refreshCoverageCaches(
                MetalOrder::query()->findOrFail($allocation->metal_order_id)
            );

            return $allocation->fresh();
        });
    }

    public function initializeCoverageFields(MetalOrder $order): void
    {
        if ($order->order_type !== 'buy') {
            return;
        }

        if ($order->coverage_status !== null && $order->covered_qty !== null && $order->open_qty !== null) {
            if (!$order->coverage_deadline) {
                $order->coverage_deadline = $this->maturity->coverageDeadlineForOrder($order);
                $order->save();
            }

            return;
        }

        $qty = $this->ledger->decimal($order->qty, MetalTreasuryLedgerService::METAL_SCALE, 'qty');

        // بدون تخصیص واقعی، حتی سفارش succeed هم کسری باز دارد و باید در صفحه پوشش دیده شود.
        $order->forceFill([
            'coverage_status' => MetalCoverageStatus::UNCOVERED,
            'covered_qty' => '0',
            'open_qty' => $qty,
            'coverage_deadline' => $this->maturity->coverageDeadlineForOrder($order),
        ])->save();
    }

    public function refreshCoverageCaches(MetalOrder $order): MetalOrder
    {
        $qty = $this->ledger->decimal($order->qty, MetalTreasuryLedgerService::METAL_SCALE, 'qty');

        $covered = MetalOrderCoverageAllocation::query()
            ->where('metal_order_id', $order->id)
            ->whereNull('reversed_at')
            ->sum(DB::raw('CAST(qty AS DECIMAL(20,3))'));

        // sum() may return float; normalize via bcmath
        $covered = $this->ledger->decimal($covered ?: '0', MetalTreasuryLedgerService::METAL_SCALE, 'covered');
        $open = bcsub($qty, $covered, MetalTreasuryLedgerService::METAL_SCALE);

        if (bccomp($open, '0', MetalTreasuryLedgerService::METAL_SCALE) < 0) {
            throw new RuntimeException('covered_qty از qty سفارش بیشتر است.');
        }

        $previous = $order->coverage_status;
        $status = match (true) {
            bccomp($covered, '0', MetalTreasuryLedgerService::METAL_SCALE) === 0 => MetalCoverageStatus::UNCOVERED,
            bccomp($open, '0', MetalTreasuryLedgerService::METAL_SCALE) === 0 => MetalCoverageStatus::FULLY_COVERED,
            default => MetalCoverageStatus::PARTIALLY_COVERED,
        };

        // حفظ OVERDUE اگر هنوز کسری دارد
        if (
            $previous === MetalCoverageStatus::OVERDUE
            && $status !== MetalCoverageStatus::FULLY_COVERED
        ) {
            $status = MetalCoverageStatus::OVERDUE;
        }

        $order->forceFill([
            'covered_qty' => $covered,
            'open_qty' => $open,
            'coverage_status' => $status,
        ])->save();

        return $order->fresh();
    }

    public function markOverdueOrders(?string $asOfDate = null): int
    {
        $asOf = $asOfDate ?: now(config('app.timezone'))->toDateString();
        $count = 0;

        MetalOrder::query()
            ->where('order_type', 'buy')
            ->whereIn('coverage_status', [
                MetalCoverageStatus::UNCOVERED,
                MetalCoverageStatus::PARTIALLY_COVERED,
            ])
            ->whereNotNull('coverage_deadline')
            ->whereDate('coverage_deadline', '<', $asOf)
            ->where('open_qty', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    $order->coverage_status = MetalCoverageStatus::OVERDUE;
                    $order->save();
                    $count++;
                }
            });

        return $count;
    }

    public function totalRealizedPnl(MetalOrder $order): string
    {
        $total = '0';

        $rows = MetalOrderCoverageAllocation::query()
            ->where('metal_order_id', $order->id)
            ->whereNull('reversed_at')
            ->get(['realized_pnl']);

        foreach ($rows as $row) {
            $total = bcadd($total, (string) $row->realized_pnl, MetalTreasuryLedgerService::PRICE_SCALE);
        }

        return $total;
    }

    public function createAllocationFromLot(
        MetalOrder $order,
        MetalTreasuryLot $lot,
        string $qty,
        string $sourceType,
        string $idempotencyKey,
        ?int $sourceId = null,
        ?int $attemptId = null,
    ): MetalOrderCoverageAllocation {
        $idempotencyKey = MetalTreasuryLedgerService::normalizeIdempotencyKey($idempotencyKey);

        $existing = MetalOrderCoverageAllocation::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $customer = $this->ledger->decimal((string) (int) round((float) $order->fee), MetalTreasuryLedgerService::PRICE_SCALE, 'fee');
        $cost = $this->ledger->decimal($lot->unit_cost, MetalTreasuryLedgerService::PRICE_SCALE, 'unit_cost');
        $qty = $this->ledger->decimal($qty, MetalTreasuryLedgerService::METAL_SCALE, 'qty');
        $pnl = bcmul($qty, bcsub($customer, $cost, MetalTreasuryLedgerService::PRICE_SCALE), MetalTreasuryLedgerService::PRICE_SCALE);

        return MetalOrderCoverageAllocation::create([
            'metal_order_id' => $order->id,
            'metal_item_id' => $order->metal_item_id,
            'source_type' => $sourceType,
            'source_id' => $sourceId ?? $lot->id,
            'lot_id' => $lot->id,
            'attempt_id' => $attemptId,
            'maturity_code' => $lot->maturity_code,
            'qty' => $qty,
            'customer_unit_price' => $customer,
            'coverage_unit_cost' => $cost,
            'realized_pnl' => $pnl,
            'allocated_at' => now(),
            'allocated_by_id' => Auth::id(),
            'allocated_by_type' => $this->actorType(),
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function createPartnerAllocation(
        MetalOrder $order,
        string $qty,
        string $unitCost,
        string $maturityCode,
        string $idempotencyKey,
        ?int $attemptId = null,
        ?int $sourceId = null,
    ): MetalOrderCoverageAllocation {
        $idempotencyKey = MetalTreasuryLedgerService::normalizeIdempotencyKey($idempotencyKey);

        $existing = MetalOrderCoverageAllocation::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $customer = $this->ledger->decimal((string) (int) round((float) $order->fee), MetalTreasuryLedgerService::PRICE_SCALE, 'fee');
        $cost = $this->ledger->decimal($unitCost, MetalTreasuryLedgerService::PRICE_SCALE, 'unit_cost');
        $qty = $this->ledger->decimal($qty, MetalTreasuryLedgerService::METAL_SCALE, 'qty');
        $pnl = bcmul($qty, bcsub($customer, $cost, MetalTreasuryLedgerService::PRICE_SCALE), MetalTreasuryLedgerService::PRICE_SCALE);

        return MetalOrderCoverageAllocation::create([
            'metal_order_id' => $order->id,
            'metal_item_id' => $order->metal_item_id,
            'source_type' => MetalTreasurySourceType::PARTNER,
            'source_id' => $sourceId,
            'lot_id' => null,
            'attempt_id' => $attemptId,
            'maturity_code' => $maturityCode,
            'qty' => $qty,
            'customer_unit_price' => $customer,
            'coverage_unit_cost' => $cost,
            'realized_pnl' => $pnl,
            'allocated_at' => now(),
            'allocated_by_id' => Auth::id(),
            'allocated_by_type' => $this->actorType(),
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    private function finalizeIfFullyCovered(MetalOrder $order): void
    {
        $order = $order->fresh();

        if ($order->coverage_status !== MetalCoverageStatus::FULLY_COVERED) {
            return;
        }

        if ($order->isFinalized()) {
            return;
        }

        // مطابق تصمیم تأییدشده: فقط پس از پوشش کامل succeed می‌شود
        try {
            MetalOrderService::markAsSucceed($order, [
                'succeed_at' => now(),
                'coverage_finalized' => true,
            ]);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        try {
            MetalOrderService::updateStock($order->fresh());
        } catch (Throwable $e) {
            // موجودی اسکالر قدیمی ممکن است در همه محیط‌ها نباشد
            report($e);
        }
    }

    private function assertBuyOpen(MetalOrder $order): void
    {
        if ($order->order_type !== 'buy') {
            throw new LogicException('پوشش فقط برای سفارش خرید است.');
        }

        if ($order->status === 'rejected') {
            throw new LogicException('سفارش ردشده قابل پوشش نیست.');
        }

        if (bccomp((string) ($order->open_qty ?? '0'), '0', MetalTreasuryLedgerService::METAL_SCALE) <= 0) {
            throw new LogicException('کسری بازی برای تخصیص وجود ندارد.');
        }
    }

    private function assertNotOverOpen(MetalOrder $order, string $qty): void
    {
        if (bccomp($qty, (string) $order->open_qty, MetalTreasuryLedgerService::METAL_SCALE) > 0) {
            throw new LogicException('مقدار تخصیص از کسری باز بیشتر است.');
        }
    }

    private function assertLotCompatible(MetalOrder $order, MetalTreasuryLot $lot, array $allowed): void
    {
        if (!$this->maturity->isSourceMetalItemAllowed($order, (int) $lot->metal_item_id)) {
            throw new LogicException('فلز Lot با سفارش سازگار نیست.');
        }

        if (!$this->maturity->isSourceAllowed(
            $this->maturity->codeForOrder($order),
            (string) $lot->maturity_code
        )) {
            throw new LogicException('سررسید Lot برای این سفارش مجاز نیست.');
        }

        if (!in_array($lot->maturity_code, $allowed, true)) {
            throw new LogicException('سررسید منبع خارج از اولویت مجاز است.');
        }
    }

    private function actorType(): ?string
    {
        if (!Auth::check()) {
            return null;
        }

        $user = Auth::user();

        return method_exists($user, 'getMorphClass')
            ? $user->getMorphClass()
            : class_basename($user);
    }
}
