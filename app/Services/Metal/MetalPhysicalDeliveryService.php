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

        return $rate;
    }

    public function updateFeeRatePercent(string $percent): string
    {
        $rate = $this->decimal($percent, self::FEE_RATE_SCALE, 'fee_rate_percent');
        if (bccomp($rate, '0', self::FEE_RATE_SCALE) < 0) {
            throw new InvalidArgumentException('نرخ کارمزد نامعتبر است.');
        }

        $setting = Setting::query()->where('option_key', self::SETTING_FEE_PERCENT)->first();
        if (!$setting) {
            DB::table('settings')->insert([
                'option_key' => self::SETTING_FEE_PERCENT,
                'option_name' => 'نرخ کارمزد تحویل فیزیکی فلز (درصد)',
                'option_value' => $rate,
            ]);
        } else {
            $setting->option_value = $rate;
            $setting->save();
        }

        return $rate;
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
        return DB::transaction(function () use ($request, $reason) {
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
    }

    public function markReady(MetalPhysicalDeliveryRequest $request): MetalPhysicalDeliveryRequest
    {
        return DB::transaction(function () use ($request) {
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
    }

    /**
     * @param  array{source:string,lot_id?:int|null,notes?:string|null,idempotency_key?:string|null}  $data
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

        return DB::transaction(function () use ($request, $data, $source, $deliverKey) {
            $request = MetalPhysicalDeliveryRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($deliverKey && $request->deliver_idempotency_key === $deliverKey
                && $request->status === MetalPhysicalDeliveryStatus::DELIVERED
            ) {
                return $request->fresh(['metalItem', 'wallet', 'trader', 'treasuryLot']);
            }

            if ($request->status === MetalPhysicalDeliveryStatus::DELIVERED) {
                throw new LogicException('این درخواست قبلاً تحویل شده است.');
            }

            $from = $request->status;
            if (!MetalPhysicalDeliveryStatus::canTransition($from, MetalPhysicalDeliveryStatus::DELIVERED)) {
                throw new LogicException('گذار به تحویل‌شده از وضعیت فعلی مجاز نیست.');
            }

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
                        qty: (string) $request->qty,
                        idempotencyKey: $outKey,
                        physicalDeliveryRequestId: (int) $request->id,
                        actorId: Auth::id(),
                        actorType: 'admin',
                        meta: ['notes' => $data['notes'] ?? null],
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

            $this->consumeReservedMetal($wallet, $request);

            $request->forceFill([
                'status' => MetalPhysicalDeliveryStatus::DELIVERED,
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
                    'fee_recognized_at' => now()->toIso8601String(),
                ],
                Auth::id(),
                'admin'
            );

            return $request->fresh(['metalItem', 'wallet', 'trader', 'treasuryLot']);
        }, 3);
    }

    private function cancel(
        MetalPhysicalDeliveryRequest $request,
        ?int $actorId,
        string $actorType,
        string $reason,
    ): MetalPhysicalDeliveryRequest {
        return DB::transaction(function () use ($request, $actorId, $actorType, $reason) {
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

    private function consumeReservedMetal(MetalTraderWallet $wallet, MetalPhysicalDeliveryRequest $request): void
    {
        if ($request->metal_consumed_at) {
            return;
        }

        $qty = $this->decimal((string) $request->qty, self::METAL_SCALE, 'qty');
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
            'reference_number' => 'PDR:' . $request->id . ':metal-consume',
            'description' => sprintf('کسر قطعی وزن رزروشده پس از تحویل فیزیکی #%s', $request->tracking_code),
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
