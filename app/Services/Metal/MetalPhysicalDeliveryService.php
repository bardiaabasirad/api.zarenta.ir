<?php

namespace App\Services\Metal;

use App\Constants\MetalPhysicalDeliverySource;
use App\Constants\MetalPhysicalDeliveryStatus;
use App\Constants\MetalTreasurySourceType;
use App\Models\MetalItem;
use App\Models\MetalPhysicalDeliveryEvent;
use App\Models\MetalPhysicalDeliveryRequest;
use App\Models\MetalTrader;
use App\Models\MetalTraderWallet;
use App\Models\MetalTreasuryLot;
use App\Models\SelectedMetalPrice;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Services\MetalPriceService;
use App\Services\MetalTraderNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

class MetalPhysicalDeliveryService
{
    public const METAL_SCALE = 3;
    public const FIAT_SCALE = 0;
    public const FEE_RATE_SCALE = 6;
    public const SETTING_FEE_PERCENT = 'physical_delivery_fee_percent';

    public function __construct(
        private readonly MetalTreasuryLedgerService $ledger,
        private readonly MetalPriceService $priceService,
        private readonly MetalTraderNotificationService $traderNotificationService,
    ) {
    }

    public function feeRatePercent(): string
    {
        $raw = Setting::query()
            ->where('option_key', self::SETTING_FEE_PERCENT)
            ->value('option_value');

        $rate = $this->decimal($raw ?? '0.5', self::FEE_RATE_SCALE, 'fee_rate_percent');

        if (bccomp($rate, '0', self::FEE_RATE_SCALE) < 0) {
            throw new LogicException('نرخ کارمزد تحویل نمی‌تواند منفی باشد.');
        }

        return $this->trimTrailingZeros($rate);
    }

    public function updateFeeRatePercent(string $percent): string
    {
        $rate = $this->decimal($percent, self::FEE_RATE_SCALE, 'fee_rate_percent');
        if (bccomp($rate, '0', self::FEE_RATE_SCALE) < 0) {
            throw new InvalidArgumentException('نرخ کارمزد نامعتبر است.');
        }

        $displayRate = $this->trimTrailingZeros($rate);

        $setting = Setting::query()->where('option_key', self::SETTING_FEE_PERCENT)->first();
        if (!$setting) {
            DB::table('settings')->insert([
                'option_key' => self::SETTING_FEE_PERCENT,
                'option_name' => 'نرخ کارمزد تحویل فیزیکی فلز (درصد)',
                'option_value' => $displayRate,
            ]);
        } else {
            $setting->option_value = $displayRate;
            $setting->save();
        }

        return $displayRate;
    }

    /**
     * @return array{fee_rate_percent:string,fee_unit_price:string,fee_amount:string,fee_pricing_unit_id:string,sell_price:string,notional:string}
     */
    public function quoteFee(MetalItem $item, string $qty): array
    {
        $qty = $this->decimal($qty, self::METAL_SCALE, 'qty');
        $this->assertPositive($qty, self::METAL_SCALE, 'qty');

        $latest = SelectedMetalPrice::query()
            ->where('metal_item_id', $item->id)
            ->latest('id')
            ->first();

        if (!$latest || $latest->sell === null || $latest->sell === '') {
            throw new LogicException('قیمت فروش معتبر برای این فلز یافت نشد.');
        }

        $pricingUnitId = (string) ($item->pricing_unit_id ?? 'mazane');
        $physicalUnit = (string) ($item->unit ?? 'gram');
        $sell = $this->decimal((string) $latest->sell, 0, 'sell');
        $unitPrice = $this->decimal(
            $this->priceService->pricePerPhysicalUnit($sell, $pricingUnitId, $physicalUnit),
            0,
            'fee_unit_price'
        );

        $ratePercent = $this->feeRatePercent();
        $notional = bcmul($qty, $unitPrice, 6);
        $feeExact = bcmul($notional, bcdiv($ratePercent, '100', 10), 6);
        $feeAmount = $this->roundFiat($feeExact);

        return [
            'fee_rate_percent' => $ratePercent,
            'fee_unit_price' => $unitPrice,
            'fee_amount' => $feeAmount,
            'fee_pricing_unit_id' => $pricingUnitId,
            'sell_price' => $sell,
            'notional' => $this->roundFiat($notional),
        ];
    }

    public function create(
        MetalTrader $trader,
        int $metalItemId,
        string $qty,
        ?string $idempotencyKey = null,
        ?string $notes = null,
    ): MetalPhysicalDeliveryRequest {
        $idempotencyKey = $idempotencyKey
            ? MetalTreasuryLedgerService::normalizeIdempotencyKey($idempotencyKey)
            : null;

        if ($idempotencyKey) {
            $existing = MetalPhysicalDeliveryRequest::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($trader, $metalItemId, $qty, $idempotencyKey, $notes) {
            if ($idempotencyKey) {
                $again = MetalPhysicalDeliveryRequest::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($again) {
                    return $again;
                }
            }

            $item = MetalItem::query()
                ->withoutGlobalScopes()
                ->whereKey($metalItemId)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$item->is_spot) {
                throw new LogicException('فقط برای فلز حاضر (T0 / is_spot) می‌توان درخواست تحویل ثبت کرد.');
            }

            if (!$item->allows_physical_delivery) {
                throw new LogicException('این فلز قابلیت تحویل فیزیکی ندارد.');
            }

            $qty = $this->decimal($qty, self::METAL_SCALE, 'qty');
            $this->assertPositive($qty, self::METAL_SCALE, 'qty');

            $wallet = MetalTraderWallet::query()
                ->where('metal_trader_id', $trader->id)
                ->where('metal_item_id', $item->id)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new LogicException('کیف‌پول فلزی برای این قلم یافت نشد.');
            }

            $available = $this->decimal((string) ($wallet->available_balance ?? '0'), self::METAL_SCALE, 'available');
            if (bccomp($available, $qty, self::METAL_SCALE) < 0) {
                throw new LogicException('موجودی آزاد فلز حاضر کافی نیست.');
            }

            $fee = $this->quoteFee($item, $qty);

            $request = MetalPhysicalDeliveryRequest::create([
                'tracking_code' => $this->generateTrackingCode(),
                'metal_trader_id' => $trader->id,
                'metal_item_id' => $item->id,
                'metal_trader_wallet_id' => $wallet->id,
                'qty' => $qty,
                'unit' => $item->unit ?? 'gram',
                'status' => MetalPhysicalDeliveryStatus::PENDING,
                'fee_rate_percent' => $fee['fee_rate_percent'],
                'fee_unit_price' => $fee['fee_unit_price'],
                'fee_pricing_unit_id' => $fee['fee_pricing_unit_id'],
                'fee_amount' => $fee['fee_amount'],
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
                'meta' => [
                    'sell_price_snapshot' => $fee['sell_price'],
                    'notional_snapshot' => $fee['notional'],
                ],
            ]);

            $this->reserveMetal($wallet, $request);
            $feeTx = $this->chargeFeeAllowingNegative($trader, $request);
            $request->forceFill([
                'fee_wallet_tx_id' => $feeTx->id,
                'metal_reserved_at' => now(),
            ])->save();

            $this->recordEvent($request, 'created', null, MetalPhysicalDeliveryStatus::PENDING, [
                'qty' => $qty,
                'fee_amount' => $fee['fee_amount'],
            ], $trader->id, 'metal_trader');

            return $request->fresh(['metalItem', 'wallet', 'trader']);
        }, 3);
    }

    public function cancelByTrader(MetalPhysicalDeliveryRequest $request, MetalTrader $trader): MetalPhysicalDeliveryRequest
    {
        if ((int) $request->metal_trader_id !== (int) $trader->id) {
            abort(403, 'دسترسی به این درخواست ندارید.');
        }

        return $this->cancel($request, $trader->id, 'metal_trader', 'لغو توسط کاربر');
    }

    public function cancelByAdmin(MetalPhysicalDeliveryRequest $request, ?string $reason = null): MetalPhysicalDeliveryRequest
    {
        return $this->cancel($request, Auth::id(), 'admin', $reason ?: 'لغو توسط ادمین');
    }

    public function reject(MetalPhysicalDeliveryRequest $request, ?string $reason = null): MetalPhysicalDeliveryRequest
    {
        $request = DB::transaction(function () use ($request, $reason) {
            $request = MetalPhysicalDeliveryRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $from = $request->status;
            if (!MetalPhysicalDeliveryStatus::canTransition($from, MetalPhysicalDeliveryStatus::REJECTED)) {
                throw new LogicException('از وضعیت فعلی امکان رد وجود ندارد.');
            }

            $this->releaseMetalAndRefundFee($request);

            $request->forceFill([
                'status' => MetalPhysicalDeliveryStatus::REJECTED,
                'rejected_by' => Auth::id(),
                'rejected_at' => now(),
                'reject_reason' => $reason,
            ])->save();

            $this->recordEvent(
                $request,
                'rejected',
                $from,
                MetalPhysicalDeliveryStatus::REJECTED,
                ['reason' => $reason],
                Auth::id(),
                'admin'
            );

            return $request->fresh(['metalItem', 'wallet', 'trader']);
        }, 3);

        $this->notifyTraderStatusChange($request, MetalPhysicalDeliveryStatus::REJECTED, $reason);

        return $request;
    }

    public function markReady(MetalPhysicalDeliveryRequest $request): MetalPhysicalDeliveryRequest
    {
        $request = DB::transaction(function () use ($request) {
            $request = MetalPhysicalDeliveryRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $from = $request->status;
            if (!MetalPhysicalDeliveryStatus::canTransition($from, MetalPhysicalDeliveryStatus::READY_FOR_PICKUP)) {
                throw new LogicException('گذار به آماده تحویل از وضعیت فعلی مجاز نیست.');
            }

            $request->forceFill([
                'status' => MetalPhysicalDeliveryStatus::READY_FOR_PICKUP,
                'prepared_by' => Auth::id(),
                'prepared_at' => now(),
            ])->save();

            $this->recordEvent(
                $request,
                'ready_for_pickup',
                $from,
                MetalPhysicalDeliveryStatus::READY_FOR_PICKUP,
                [],
                Auth::id(),
                'admin'
            );

            return $request->fresh(['metalItem', 'wallet', 'trader']);
        }, 3);

        $this->notifyTraderStatusChange($request, MetalPhysicalDeliveryStatus::READY_FOR_PICKUP);

        return $request;
    }

    /**
     * @param  array{source:string,lot_id?:int|null,delivered_qty?:string|null,notes?:string|null,idempotency_key?:string|null}  $data
     */
    public function deliver(MetalPhysicalDeliveryRequest $request, array $data): MetalPhysicalDeliveryRequest
    {
        $source = $data['source'] ?? null;
        if (!in_array($source, MetalPhysicalDeliverySource::all(), true)) {
            throw new InvalidArgumentException('منبع تحویل نامعتبر است.');
        }

        $deliverKey = isset($data['idempotency_key']) && $data['idempotency_key']
            ? MetalTreasuryLedgerService::normalizeIdempotencyKey((string) $data['idempotency_key'])
            : null;

        $alreadyDelivered = false;

        $request = DB::transaction(function () use ($request, $data, $source, $deliverKey, &$alreadyDelivered) {
            $request = MetalPhysicalDeliveryRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($deliverKey && $request->deliver_idempotency_key === $deliverKey
                && $request->status === MetalPhysicalDeliveryStatus::DELIVERED
            ) {
                $alreadyDelivered = true;

                return $request->fresh(['metalItem', 'wallet', 'trader', 'treasuryLot']);
            }

            if ($request->status === MetalPhysicalDeliveryStatus::DELIVERED) {
                throw new LogicException('این درخواست قبلاً تحویل شده است.');
            }

            $from = $request->status;
            if (!MetalPhysicalDeliveryStatus::canTransition($from, MetalPhysicalDeliveryStatus::DELIVERED)) {
                throw new LogicException('گذار به تحویل‌شده از وضعیت فعلی مجاز نیست.');
            }

            $requestedQty = $this->decimal((string) $request->qty, self::METAL_SCALE, 'qty');
            $unit = (string) ($request->unit ?? 'gram');
            $deliveredQty = $this->resolveDeliveredQty($data, $requestedQty, $unit);
            $qtyDelta = bcsub($deliveredQty, $requestedQty, self::METAL_SCALE);
            $feeAdjustment = $this->feeAdjustmentForDelta($request, $qtyDelta);

            $movementId = null;
            $lotId = null;

            if ($source === MetalPhysicalDeliverySource::TREASURY_PHYSICAL) {
                $lotId = (int) ($data['lot_id'] ?? 0);
                if ($lotId <= 0) {
                    throw new InvalidArgumentException('برای تحویل از خزانه انتخاب Lot الزامی است.');
                }

                $lot = MetalTreasuryLot::query()
                    ->whereKey($lotId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $lot->metal_item_id !== (int) $request->metal_item_id) {
                    throw new LogicException('Lot انتخابی با فلز درخواست هم‌خوان نیست.');
                }

                if ($lot->source_type !== MetalTreasurySourceType::PHYSICAL_PURCHASE) {
                    throw new LogicException('فقط Lot خرید حضوری برای تحویل از خزانه مجاز است.');
                }

                $outKey = $deliverKey
                    ?: MetalTreasuryLedgerService::normalizeIdempotencyKey('pdo:' . $request->id . ':' . $lot->id);

                try {
                    $movement = $this->ledger->physicalDeliveryOut(
                        lot: $lot,
                        qty: $deliveredQty,
                        idempotencyKey: $outKey,
                        physicalDeliveryRequestId: (int) $request->id,
                        actorId: Auth::id(),
                        actorType: 'admin',
                        meta: [
                            'notes' => $data['notes'] ?? null,
                            'requested_qty' => $requestedQty,
                            'delivered_qty' => $deliveredQty,
                        ],
                    );
                } catch (LogicException $e) {
                    // وزن کافی نیست → درخواست در وضعیت قبلی می‌ماند (تراکنش rollback)
                    throw $e;
                }

                $movementId = $movement->id;
                $lotId = $lot->id;
            } else {
                // SHOP_UNREGISTERED: فقط audit — بدون کسر خزانه/بدهی/سود
                $movementId = null;
                $lotId = null;
            }

            $wallet = MetalTraderWallet::query()
                ->whereKey($request->metal_trader_wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->settleMetalOnDeliver($wallet, $request, $requestedQty, $deliveredQty);
            $feeAdjTx = $this->applyFeeAdjustment($request, $feeAdjustment);

            $request->forceFill([
                'status' => MetalPhysicalDeliveryStatus::DELIVERED,
                'delivered_qty' => $deliveredQty,
                'fee_adjustment_amount' => $feeAdjustment,
                'fee_adjustment_wallet_tx_id' => $feeAdjTx?->id,
                'delivery_source' => $source,
                'treasury_lot_id' => $lotId,
                'treasury_movement_id' => $movementId,
                'delivered_by' => Auth::id(),
                'delivered_at' => now(),
                'fee_recognized_at' => now(),
                'metal_consumed_at' => now(),
                'deliver_idempotency_key' => $deliverKey,
                'notes' => $data['notes'] ?? $request->notes,
                'meta' => array_merge($request->meta ?? [], [
                    'delivery_source' => $source,
                    'shop_unregistered_audit' => $source === MetalPhysicalDeliverySource::SHOP_UNREGISTERED,
                    'requested_qty' => $requestedQty,
                    'delivered_qty' => $deliveredQty,
                    'qty_delta' => $qtyDelta,
                    'fee_adjustment_amount' => $feeAdjustment,
                    'fee_final_amount' => bcadd(
                        $this->decimal((string) $request->fee_amount, self::FIAT_SCALE, 'fee_amount'),
                        $feeAdjustment,
                        self::FIAT_SCALE
                    ),
                ]),
            ])->save();

            $this->recordEvent(
                $request,
                'delivered',
                $from,
                MetalPhysicalDeliveryStatus::DELIVERED,
                [
                    'source' => $source,
                    'lot_id' => $lotId,
                    'treasury_movement_id' => $movementId,
                    'requested_qty' => $requestedQty,
                    'delivered_qty' => $deliveredQty,
                    'qty_delta' => $qtyDelta,
                    'fee_adjustment_amount' => $feeAdjustment,
                    'fee_recognized_at' => now()->toIso8601String(),
                ],
                Auth::id(),
                'admin'
            );

            return $request->fresh(['metalItem', 'wallet', 'trader', 'treasuryLot']);
        }, 3);

        if (!$alreadyDelivered) {
            $this->notifyTraderStatusChange($request, MetalPhysicalDeliveryStatus::DELIVERED);
        }

        return $request;
    }

    /**
     * وزن واقعی تحویل برای محصولات گرمی اجباری است؛ برای تعدادی باید برابر qty باشد.
     */
    private function resolveDeliveredQty(
        array $data,
        string $requestedQty,
        string $unit,
    ): string {
        $raw = $data['delivered_qty'] ?? null;

        if ($unit === 'count') {
            if ($raw === null || $raw === '') {
                return $requestedQty;
            }
            $delivered = $this->decimal((string) $raw, self::METAL_SCALE, 'delivered_qty');
            if (bccomp($delivered, $requestedQty, self::METAL_SCALE) !== 0) {
                throw new InvalidArgumentException('برای محصولات تعدادی، مقدار تحویل باید دقیقاً برابر مقدار درخواست باشد.');
            }

            return $delivered;
        }

        // gram (و سایر واحدهای وزنی): اپراتور باید وزن واقعی را وارد کند
        if ($raw === null || $raw === '') {
            throw new InvalidArgumentException('وزن واقعی تحویل‌شده الزامی است.');
        }

        $delivered = $this->decimal((string) $raw, self::METAL_SCALE, 'delivered_qty');
        $this->assertPositive($delivered, self::METAL_SCALE, 'delivered_qty');

        return $delivered;
    }

    /**
     * تعدیل کارمزد بر اساس اختلاف وزن و نرخ/قیمت snapshot درخواست.
     * مثبت = کسر اضافه از کیف ریالی؛ منفی = استرداد.
     */
    private function feeAdjustmentForDelta(MetalPhysicalDeliveryRequest $request, string $qtyDelta): string
    {
        if (bccomp($qtyDelta, '0', self::METAL_SCALE) === 0) {
            return '0';
        }

        $absDelta = bccomp($qtyDelta, '0', self::METAL_SCALE) < 0
            ? bcsub('0', $qtyDelta, self::METAL_SCALE)
            : $qtyDelta;

        $unitPrice = $this->decimal((string) $request->fee_unit_price, 0, 'fee_unit_price');
        $ratePercent = $this->decimal((string) $request->fee_rate_percent, self::FEE_RATE_SCALE, 'fee_rate_percent');
        $notional = bcmul($absDelta, $unitPrice, 6);
        $feeExact = bcmul($notional, bcdiv($ratePercent, '100', 10), 6);
        $absFee = $this->roundFiat($feeExact);

        if (bccomp($qtyDelta, '0', self::METAL_SCALE) < 0) {
            return bcmul($absFee, '-1', self::FIAT_SCALE);
        }

        return $absFee;
    }

    private function cancel(
        MetalPhysicalDeliveryRequest $request,
        ?int $actorId,
        string $actorType,
        string $reason,
    ): MetalPhysicalDeliveryRequest {
        $request = DB::transaction(function () use ($request, $actorId, $actorType, $reason) {
            $request = MetalPhysicalDeliveryRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $from = $request->status;
            if (!MetalPhysicalDeliveryStatus::canTransition($from, MetalPhysicalDeliveryStatus::CANCELLED)) {
                throw new LogicException('لغو از وضعیت فعلی مجاز نیست.');
            }

            $this->releaseMetalAndRefundFee($request);

            $request->forceFill([
                'status' => MetalPhysicalDeliveryStatus::CANCELLED,
                'cancelled_by' => $actorId,
                'cancelled_by_type' => $actorType,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            $this->recordEvent(
                $request,
                'cancelled',
                $from,
                MetalPhysicalDeliveryStatus::CANCELLED,
                ['reason' => $reason],
                $actorId,
                $actorType
            );

            return $request->fresh(['metalItem', 'wallet', 'trader']);
        }, 3);

        if ($actorType === 'admin') {
            $this->notifyTraderStatusChange($request, MetalPhysicalDeliveryStatus::CANCELLED, $reason);
        }

        return $request;
    }

    private function notifyTraderStatusChange(
        MetalPhysicalDeliveryRequest $request,
        string $status,
        ?string $reason = null
    ): void {
        $request->loadMissing('metalItem');

        $metalTitle = $request->metalItem?->title ?: 'فلز';
        $qty = rtrim(rtrim((string) $request->qty, '0'), '.') ?: (string) $request->qty;
        $unit = match ($request->metalItem?->unit ?? $request->unit) {
            'count' => 'عدد',
            default => 'گرم',
        };
        $subject = $qty . ' ' . $unit . ' ' . $metalTitle;
        $deliveredSubject = $subject;
        if ($status === MetalPhysicalDeliveryStatus::DELIVERED && $request->delivered_qty !== null) {
            $deliveredQty = rtrim(rtrim((string) $request->delivered_qty, '0'), '.') ?: (string) $request->delivered_qty;
            if ($deliveredQty !== $qty) {
                $deliveredSubject = $deliveredQty . ' ' . $unit . ' ' . $metalTitle
                    . ' (درخواست: ' . $qty . ' ' . $unit . ')';
            }
        }

        [$title, $content, $type] = match ($status) {
            MetalPhysicalDeliveryStatus::READY_FOR_PICKUP => [
                'آماده تحویل فیزیکی',
                'درخواست تحویل فیزیکی شما (' . $subject . ') آماده دریافت است.',
                'physical_delivery_ready',
            ],
            MetalPhysicalDeliveryStatus::DELIVERED => [
                'تحویل فیزیکی انجام شد',
                'درخواست تحویل فیزیکی شما (' . $deliveredSubject . ') تحویل داده شد.',
                'physical_delivery_delivered',
            ],
            MetalPhysicalDeliveryStatus::REJECTED => [
                'رد درخواست تحویل فیزیکی',
                'درخواست تحویل فیزیکی شما (' . $subject . ') رد شد.'
                    . ($reason ? ' علت: ' . $reason : ''),
                'physical_delivery_rejected',
            ],
            MetalPhysicalDeliveryStatus::CANCELLED => [
                'لغو درخواست تحویل فیزیکی',
                'درخواست تحویل فیزیکی شما (' . $subject . ') توسط اپراتور لغو شد.'
                    . ($reason ? ' علت: ' . $reason : ''),
                'physical_delivery_cancelled',
            ],
            default => [null, null, null],
        };

        if (!$title) {
            return;
        }

        $this->traderNotificationService->create([
            'metal_trader_id' => $request->metal_trader_id,
            'title' => $title,
            'content' => $content,
            'link' => '/physical-deliveries',
            'type' => $type,
        ]);
    }

    private function releaseMetalAndRefundFee(MetalPhysicalDeliveryRequest $request): void
    {
        $wallet = MetalTraderWallet::query()
            ->whereKey($request->metal_trader_wallet_id)
            ->lockForUpdate()
            ->firstOrFail();

        $this->releaseMetal($wallet, $request);
        $refundTx = $this->refundFee($request);
        $request->forceFill([
            'fee_refund_wallet_tx_id' => $refundTx?->id,
            'fee_refunded_at' => $refundTx ? now() : $request->fee_refunded_at,
            'metal_released_at' => now(),
        ])->save();
    }

    private function reserveMetal(MetalTraderWallet $wallet, MetalPhysicalDeliveryRequest $request): void
    {
        $qty = $this->decimal((string) $request->qty, self::METAL_SCALE, 'qty');
        $available = $this->decimal((string) ($wallet->available_balance ?? '0'), self::METAL_SCALE, 'available');
        $blocked = $this->decimal((string) ($wallet->blocked_balance ?? '0'), self::METAL_SCALE, 'blocked');

        if (bccomp($available, $qty, self::METAL_SCALE) < 0) {
            throw new LogicException('موجودی آزاد برای رزرو کافی نیست.');
        }

        // MWAC تغییر نمی‌کند
        $wallet->forceFill([
            'available_balance' => bcsub($available, $qty, self::METAL_SCALE),
            'blocked_balance' => bcadd($blocked, $qty, self::METAL_SCALE),
        ])->save();

        WalletTransaction::create([
            'metal_trader_wallet_id' => $wallet->id,
            'metal_order_id' => null,
            'metal_physical_delivery_request_id' => $request->id,
            'amount' => $qty,
            'type' => 'block_collateral',
            'balance_before' => $available,
            'balance_after' => bcsub($available, $qty, self::METAL_SCALE),
            'reference_number' => 'PDR:' . $request->id . ':metal-block',
            'description' => sprintf('رزرو وزن درخواست تحویل فیزیکی #%s', $request->tracking_code),
        ]);
    }

    private function releaseMetal(MetalTraderWallet $wallet, MetalPhysicalDeliveryRequest $request): void
    {
        if ($request->metal_released_at || $request->metal_consumed_at) {
            return;
        }

        $qty = $this->decimal((string) $request->qty, self::METAL_SCALE, 'qty');
        $available = $this->decimal((string) ($wallet->available_balance ?? '0'), self::METAL_SCALE, 'available');
        $blocked = $this->decimal((string) ($wallet->blocked_balance ?? '0'), self::METAL_SCALE, 'blocked');

        if (bccomp($blocked, $qty, self::METAL_SCALE) < 0) {
            throw new RuntimeException('blocked_balance برای آزادسازی رزرو کافی نیست.');
        }

        $wallet->forceFill([
            'available_balance' => bcadd($available, $qty, self::METAL_SCALE),
            'blocked_balance' => bcsub($blocked, $qty, self::METAL_SCALE),
        ])->save();

        WalletTransaction::create([
            'metal_trader_wallet_id' => $wallet->id,
            'metal_order_id' => null,
            'metal_physical_delivery_request_id' => $request->id,
            'amount' => $qty,
            'type' => 'unblock_collateral',
            'balance_before' => $available,
            'balance_after' => bcadd($available, $qty, self::METAL_SCALE),
            'reference_number' => 'PDR:' . $request->id . ':metal-unblock',
            'description' => sprintf('آزادسازی رزرو درخواست تحویل فیزیکی #%s', $request->tracking_code),
        ]);
    }

    /**
     * تسویه کیف فلزی هنگام تحویل:
     * - کمتر از درخواست: مصرف delivered از blocked + آزادسازی مابقی به available
     * - بیشتر از درخواست: مصرف کامل رزرو + کسر مازاد از available
     * - برابر: مصرف کامل رزرو
     */
    private function settleMetalOnDeliver(
        MetalTraderWallet $wallet,
        MetalPhysicalDeliveryRequest $request,
        string $requestedQty,
        string $deliveredQty,
    ): void {
        if ($request->metal_consumed_at) {
            return;
        }

        $cmp = bccomp($deliveredQty, $requestedQty, self::METAL_SCALE);

        if ($cmp === 0) {
            $this->consumeFromBlocked($wallet, $request, $requestedQty, 'metal-consume', 'کسر قطعی وزن رزروشده پس از تحویل فیزیکی');

            return;
        }

        if ($cmp < 0) {
            $shortfall = bcsub($requestedQty, $deliveredQty, self::METAL_SCALE);
            $this->consumeFromBlocked($wallet, $request, $deliveredQty, 'metal-consume', 'کسر قطعی وزن تحویل‌شده پس از تحویل فیزیکی');
            $this->unblockPartial($wallet, $request, $shortfall);

            return;
        }

        $overage = bcsub($deliveredQty, $requestedQty, self::METAL_SCALE);
        $this->consumeFromBlocked($wallet, $request, $requestedQty, 'metal-consume', 'کسر قطعی وزن رزروشده پس از تحویل فیزیکی');
        $this->consumeFromAvailable($wallet, $request, $overage);
    }

    private function consumeFromBlocked(
        MetalTraderWallet $wallet,
        MetalPhysicalDeliveryRequest $request,
        string $qty,
        string $refSuffix,
        string $descriptionPrefix,
    ): void {
        if (bccomp($qty, '0', self::METAL_SCALE) === 0) {
            return;
        }

        $blocked = $this->decimal((string) ($wallet->blocked_balance ?? '0'), self::METAL_SCALE, 'blocked');

        if (bccomp($blocked, $qty, self::METAL_SCALE) < 0) {
            throw new RuntimeException('وزن رزروشده برای تحویل کافی نیست.');
        }

        $blockedAfter = bcsub($blocked, $qty, self::METAL_SCALE);

        // مالکیت قطعی کم می‌شود؛ MWAC بدون تغییر
        $wallet->forceFill([
            'blocked_balance' => $blockedAfter,
        ])->save();

        WalletTransaction::create([
            'metal_trader_wallet_id' => $wallet->id,
            'metal_order_id' => null,
            'metal_physical_delivery_request_id' => $request->id,
            'amount' => $qty,
            'type' => 'metal_commitment_out',
            'balance_before' => $blocked,
            'balance_after' => $blockedAfter,
            'reference_number' => 'PDR:' . $request->id . ':' . $refSuffix,
            'description' => sprintf('%s #%s', $descriptionPrefix, $request->tracking_code),
        ]);
    }

    private function unblockPartial(
        MetalTraderWallet $wallet,
        MetalPhysicalDeliveryRequest $request,
        string $qty,
    ): void {
        if (bccomp($qty, '0', self::METAL_SCALE) === 0) {
            return;
        }

        $available = $this->decimal((string) ($wallet->available_balance ?? '0'), self::METAL_SCALE, 'available');
        $blocked = $this->decimal((string) ($wallet->blocked_balance ?? '0'), self::METAL_SCALE, 'blocked');

        if (bccomp($blocked, $qty, self::METAL_SCALE) < 0) {
            throw new RuntimeException('blocked_balance برای آزادسازی مابقی تحویل کافی نیست.');
        }

        $wallet->forceFill([
            'available_balance' => bcadd($available, $qty, self::METAL_SCALE),
            'blocked_balance' => bcsub($blocked, $qty, self::METAL_SCALE),
        ])->save();

        WalletTransaction::create([
            'metal_trader_wallet_id' => $wallet->id,
            'metal_order_id' => null,
            'metal_physical_delivery_request_id' => $request->id,
            'amount' => $qty,
            'type' => 'unblock_collateral',
            'balance_before' => $available,
            'balance_after' => bcadd($available, $qty, self::METAL_SCALE),
            'reference_number' => 'PDR:' . $request->id . ':metal-partial-unblock',
            'description' => sprintf('بازگشت مابقی وزن تحویل فیزیکی #%s به موجودی آزاد', $request->tracking_code),
        ]);
    }

    private function consumeFromAvailable(
        MetalTraderWallet $wallet,
        MetalPhysicalDeliveryRequest $request,
        string $qty,
    ): void {
        if (bccomp($qty, '0', self::METAL_SCALE) === 0) {
            return;
        }

        $available = $this->decimal((string) ($wallet->available_balance ?? '0'), self::METAL_SCALE, 'available');

        if (bccomp($available, $qty, self::METAL_SCALE) < 0) {
            throw new LogicException('موجودی آزاد فلز برای پوشش وزن بیشتر از درخواست کافی نیست.');
        }

        $availableAfter = bcsub($available, $qty, self::METAL_SCALE);

        $wallet->forceFill([
            'available_balance' => $availableAfter,
        ])->save();

        WalletTransaction::create([
            'metal_trader_wallet_id' => $wallet->id,
            'metal_order_id' => null,
            'metal_physical_delivery_request_id' => $request->id,
            'amount' => $qty,
            'type' => 'metal_commitment_out',
            'balance_before' => $available,
            'balance_after' => $availableAfter,
            'reference_number' => 'PDR:' . $request->id . ':metal-over-consume',
            'description' => sprintf('کسر مازاد وزن تحویل فیزیکی #%s از موجودی آزاد', $request->tracking_code),
        ]);
    }

    /**
     * @return WalletTransaction|null تراکنش تعدیل کارمزد (استرداد یا کسر اضافه)
     */
    private function applyFeeAdjustment(MetalPhysicalDeliveryRequest $request, string $feeAdjustment): ?WalletTransaction
    {
        if (bccomp($feeAdjustment, '0', self::FIAT_SCALE) === 0) {
            return null;
        }

        $traderId = (int) $request->metal_trader_id;
        $fiatWallet = $this->lockOrCreateFiatWallet($traderId);
        $before = $this->decimal((string) ($fiatWallet->available_balance ?? '0'), self::FIAT_SCALE, 'fiat');

        if (bccomp($feeAdjustment, '0', self::FIAT_SCALE) < 0) {
            $refund = bcmul($feeAdjustment, '-1', self::FIAT_SCALE);
            $after = bcadd($before, $refund, self::FIAT_SCALE);
            $fiatWallet->available_balance = $after;
            $fiatWallet->save();

            return WalletTransaction::create([
                'metal_trader_wallet_id' => $fiatWallet->id,
                'metal_order_id' => null,
                'metal_physical_delivery_request_id' => $request->id,
                'amount' => $refund,
                'type' => 'deposit',
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_number' => 'PDR:' . $request->id . ':fee-partial-refund',
                'description' => sprintf('استرداد کارمزد مابقی تحویل فیزیکی #%s', $request->tracking_code),
            ]);
        }

        // مازاد وزن → کسر کارمزد اضافه (منفی شدن موجودی ریالی مجاز، مشابه ایجاد درخواست)
        $after = bcsub($before, $feeAdjustment, self::FIAT_SCALE);
        $fiatWallet->available_balance = $after;
        $fiatWallet->save();

        return WalletTransaction::create([
            'metal_trader_wallet_id' => $fiatWallet->id,
            'metal_order_id' => null,
            'metal_physical_delivery_request_id' => $request->id,
            'amount' => $feeAdjustment,
            'type' => 'withdraw',
            'balance_before' => $before,
            'balance_after' => $after,
            'reference_number' => 'PDR:' . $request->id . ':fee-overcharge',
            'description' => sprintf('کارمزد مازاد وزن تحویل فیزیکی #%s', $request->tracking_code),
        ]);
    }

    private function chargeFeeAllowingNegative(MetalTrader $trader, MetalPhysicalDeliveryRequest $request): WalletTransaction
    {
        $existing = WalletTransaction::query()
            ->where('metal_physical_delivery_request_id', $request->id)
            ->where('type', 'withdraw')
            ->where('reference_number', 'PDR:' . $request->id . ':fee')
            ->first();
        if ($existing) {
            return $existing;
        }

        $fiatWallet = $this->lockOrCreateFiatWallet($trader->id);
        $amount = $this->decimal((string) $request->fee_amount, self::FIAT_SCALE, 'fee_amount');
        $before = $this->decimal((string) ($fiatWallet->available_balance ?? '0'), self::FIAT_SCALE, 'fiat');
        // مجوز منفی فقط برای این عملیات
        $after = bcsub($before, $amount, self::FIAT_SCALE);

        $fiatWallet->available_balance = $after;
        $fiatWallet->save();

        return WalletTransaction::create([
            'metal_trader_wallet_id' => $fiatWallet->id,
            'metal_order_id' => null,
            'metal_physical_delivery_request_id' => $request->id,
            'amount' => $amount,
            'type' => 'withdraw',
            'balance_before' => $before,
            'balance_after' => $after,
            'reference_number' => 'PDR:' . $request->id . ':fee',
            'description' => sprintf('کارمزد درخواست تحویل فیزیکی #%s (قابل استرداد تا قبل از تحویل)', $request->tracking_code),
        ]);
    }

    private function refundFee(MetalPhysicalDeliveryRequest $request): ?WalletTransaction
    {
        if ($request->fee_refunded_at || $request->fee_refund_wallet_tx_id) {
            return WalletTransaction::query()->find($request->fee_refund_wallet_tx_id);
        }

        $existing = WalletTransaction::query()
            ->where('metal_physical_delivery_request_id', $request->id)
            ->where('type', 'deposit')
            ->where('reference_number', 'PDR:' . $request->id . ':fee-refund')
            ->first();
        if ($existing) {
            return $existing;
        }

        $amount = $this->decimal((string) $request->fee_amount, self::FIAT_SCALE, 'fee_amount');
        if (bccomp($amount, '0', self::FIAT_SCALE) === 0) {
            return null;
        }

        $traderId = (int) $request->metal_trader_id;
        $fiatWallet = $this->lockOrCreateFiatWallet($traderId);
        $before = $this->decimal((string) ($fiatWallet->available_balance ?? '0'), self::FIAT_SCALE, 'fiat');
        $after = bcadd($before, $amount, self::FIAT_SCALE);

        $fiatWallet->available_balance = $after;
        $fiatWallet->save();

        return WalletTransaction::create([
            'metal_trader_wallet_id' => $fiatWallet->id,
            'metal_order_id' => null,
            'metal_physical_delivery_request_id' => $request->id,
            'amount' => $amount,
            'type' => 'deposit',
            'balance_before' => $before,
            'balance_after' => $after,
            'reference_number' => 'PDR:' . $request->id . ':fee-refund',
            'description' => sprintf('استرداد کارمزد درخواست تحویل فیزیکی #%s', $request->tracking_code),
        ]);
    }

    private function lockOrCreateFiatWallet(int $traderId): MetalTraderWallet
    {
        $wallet = MetalTraderWallet::query()
            ->where('metal_trader_id', $traderId)
            ->whereNull('metal_item_id')
            ->lockForUpdate()
            ->first();

        if ($wallet) {
            return $wallet;
        }

        $wallet = MetalTraderWallet::query()->create([
            'metal_trader_id' => $traderId,
            'metal_item_id' => null,
            'available_balance' => '0',
            'blocked_balance' => '0',
            'fiat_balance' => '0',
            'avg_buy_price' => '0',
        ]);

        return MetalTraderWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
    }

    private function recordEvent(
        MetalPhysicalDeliveryRequest $request,
        string $eventType,
        ?string $from,
        ?string $to,
        array $payload,
        ?int $actorId,
        ?string $actorType,
    ): void {
        MetalPhysicalDeliveryEvent::create([
            'metal_physical_delivery_request_id' => $request->id,
            'event_type' => $eventType,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'payload' => $payload,
        ]);
    }

    private function generateTrackingCode(): string
    {
        do {
            $code = (string) random_int(1000000000, 9999999999);
        } while (MetalPhysicalDeliveryRequest::query()->where('tracking_code', $code)->exists());

        return $code;
    }

    private function decimal(mixed $value, int $scale, string $field): string
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException("مقدار {$field} نامعتبر است.");
        }
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("مقدار {$field} عددی نیست.");
        }

        return bcadd((string) $value, '0', $scale);
    }

    private function trimTrailingZeros(string $value): string
    {
        if (!str_contains($value, '.')) {
            return $value;
        }

        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '' || $trimmed === '-' ? '0' : $trimmed;
    }

    private function assertPositive(string $value, int $scale, string $field): void
    {
        if (bccomp($value, '0', $scale) <= 0) {
            throw new InvalidArgumentException("{$field} باید بزرگ‌تر از صفر باشد.");
        }
    }

    private function roundFiat(string $exact): string
    {
        // گرد کردن نیمه‌بالا به تومان صحیح (مقیاس ۰)
        $scaled = bcmul($exact, '1', 6);
        $asFloat = (float) $scaled;

        return (string) (int) round($asFloat, 0, PHP_ROUND_HALF_UP);
    }
}
