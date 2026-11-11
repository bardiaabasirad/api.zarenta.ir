<?php

namespace App\Services;

use App\Constants\MetalSettlementCaseStatus;
use App\Constants\MetalSettlementObligationSide;
use App\Models\MetalOrder;
use App\Models\MetalSettlementCase;
use App\Models\MetalTraderWallet;
use App\Models\SelectedMetalPrice;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class SettlementFulfillmentService
{
    public const METAL_SCALE = 3;
    public const FIAT_SCALE = 0;
    public const PENALTY_PERCENT = '1';

    /**
     * پس از اعمال سفارش نقد حاضر (T0) روی کیف‌ها، در صورت بدهکار شدن پرونده می‌سازد/به‌روز می‌کند.
     */
    public function openCaseForSpotOrder(MetalOrder $order): ?MetalSettlementCase
    {
        if ($order->created_type !== 'metal_trader' || $order->metal_item_id === null) {
            return null;
        }

        $qty = $this->decimal((string) $order->qty, self::METAL_SCALE);
        $amount = $this->decimal((string) $order->amount, self::FIAT_SCALE);
        $isBuy = $order->order_type === 'buy';

        $netMetal = $isBuy ? $qty : bcsub('0', $qty, self::METAL_SCALE);
        $netFiat = $isBuy ? bcsub('0', $amount, self::FIAT_SCALE) : $amount;

        $settlementDate = $order->settlement_date
            ? \Carbon\Carbon::parse($order->settlement_date)->toDateString()
            : now()->toDateString();

        $spotItemId = (int) $order->metal_item_id;

        return $this->openCaseIfNeeded(
            traderId: (int) $order->created_id,
            commitmentMetalItemId: $spotItemId,
            spotMetalItemId: $spotItemId,
            settlementDate: $settlementDate,
            orders: collect([$order]),
            netMetal: $netMetal,
            netFiat: $netFiat,
            accumulateNets: true,
        );
    }

    /**
     * در صورت منفی بودن کیف نقد یا فلز اسپات، پرونده open می‌سازد.
     *
     * @param  bool  $accumulateNets  برای سفارش‌های T0 متوالی true؛ برای batch سررسید T1/T2 false
     */
    public function openCaseIfNeeded(
        int $traderId,
        int $commitmentMetalItemId,
        int $spotMetalItemId,
        string $settlementDate,
        Collection $orders,
        string $netMetal,
        string $netFiat,
        bool $accumulateNets = false,
    ): ?MetalSettlementCase {
        $cashWallet = $this->getOrCreateCashWallet($traderId);
        $spotWallet = $this->getOrCreateMetalWallet($traderId, $spotMetalItemId);

        $cashBalance = $this->decimal($cashWallet->available_balance ?? '0', self::FIAT_SCALE);
        $spotBalance = $this->decimal($spotWallet->available_balance ?? '0', self::METAL_SCALE);

        $cashNegative = bccomp($cashBalance, '0', self::FIAT_SCALE) < 0;
        $metalNegative = bccomp($spotBalance, '0', self::METAL_SCALE) < 0;

        if (! $cashNegative && ! $metalNegative) {
            return null;
        }

        $side = MetalSettlementObligationSide::NONE;
        if ($cashNegative && $metalNegative) {
            $side = MetalSettlementObligationSide::BOTH;
        } elseif ($cashNegative) {
            $side = MetalSettlementObligationSide::FIAT;
        } elseif ($metalNegative) {
            $side = MetalSettlementObligationSide::METAL;
        }

        $existing = MetalSettlementCase::query()
            ->where('metal_trader_id', $traderId)
            ->where('commitment_metal_item_id', $commitmentMetalItemId)
            ->whereDate('settlement_date', $settlementDate)
            ->first();

        $incomingIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->all();
        $linkedIds = $existing
            ? $existing->orders()->whereIn('metal_orders.id', $incomingIds)->pluck('metal_orders.id')->map(fn ($id) => (int) $id)->all()
            : [];
        $newIds = array_values(array_diff($incomingIds, $linkedIds));

        $mergedNetMetal = $this->decimal($netMetal, self::METAL_SCALE);
        $mergedNetFiat = $this->decimal($netFiat, self::FIAT_SCALE);

        if (
            $accumulateNets
            && $existing
            && $existing->status === MetalSettlementCaseStatus::OPEN
            && $newIds !== []
        ) {
            $mergedNetMetal = bcadd((string) $existing->net_metal, $mergedNetMetal, self::METAL_SCALE);
            $mergedNetFiat = bcadd((string) $existing->net_fiat, $mergedNetFiat, self::FIAT_SCALE);
        } elseif (
            $accumulateNets
            && $existing
            && $existing->status === MetalSettlementCaseStatus::OPEN
            && $newIds === []
        ) {
            // فراخوانی تکراری همان سفارش‌ها: فقط اسنپ‌شات کیف را تازه کن
            $mergedNetMetal = $this->decimal((string) $existing->net_metal, self::METAL_SCALE);
            $mergedNetFiat = $this->decimal((string) $existing->net_fiat, self::FIAT_SCALE);
        }

        $case = MetalSettlementCase::query()->updateOrCreate(
            [
                'metal_trader_id' => $traderId,
                'commitment_metal_item_id' => $commitmentMetalItemId,
                'settlement_date' => $settlementDate,
            ],
            [
                'spot_metal_item_id' => $spotMetalItemId,
                'status' => MetalSettlementCaseStatus::OPEN,
                'obligation_side' => $side,
                'net_metal' => $mergedNetMetal,
                'net_fiat' => $mergedNetFiat,
                'cash_balance_after' => $cashBalance,
                'spot_metal_balance_after' => $spotBalance,
                'fulfilled_at' => null,
                'fulfilled_by' => null,
                'defaulted_at' => null,
                'defaulted_by' => null,
                'penalty_one_percent' => null,
                'replacement_loss' => null,
                'penalty_total' => null,
                'partner_trade_used' => false,
                'notes' => null,
            ]
        );

        $case->orders()->syncWithoutDetaching($incomingIds);

        return $case->fresh(['orders']);
    }

    public function deposit(MetalSettlementCase $case, string $amount, ?string $referenceNumber = null, ?string $description = null): WalletTransaction
    {
        $this->assertOpen($case);

        if (bccomp($amount, '0', self::FIAT_SCALE) <= 0) {
            throw new LogicException('مبلغ واریز باید بزرگ‌تر از صفر باشد.');
        }

        return DB::transaction(function () use ($case, $amount, $referenceNumber, $description) {
            $case = MetalSettlementCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($case);

            $wallet = $this->lockCashWallet((int) $case->metal_trader_id);
            $before = $this->decimal($wallet->available_balance ?? '0', self::FIAT_SCALE);
            $after = bcadd($before, $amount, self::FIAT_SCALE);

            $wallet->forceFill(['available_balance' => $after])->save();

            $tx = WalletTransaction::create([
                'metal_trader_wallet_id' => $wallet->id,
                'metal_order_id' => null,
                'metal_settlement_case_id' => $case->id,
                'amount' => $this->decimal($amount, self::FIAT_SCALE),
                'type' => 'deposit',
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_number' => $referenceNumber,
                'description' => $description ?: sprintf('واریز تسویه تعهد سررسید #%s', $case->id),
            ]);

            $this->autoFulfillIfCleared($case);

            return $tx->fresh();
        }, 3);
    }

    public function recordMetalDelivery(MetalSettlementCase $case, string $qty, ?string $description = null): WalletTransaction
    {
        $this->assertOpen($case);

        if (bccomp($qty, '0', self::METAL_SCALE) <= 0) {
            throw new LogicException('مقدار تحویل فلز باید بزرگ‌تر از صفر باشد.');
        }

        return DB::transaction(function () use ($case, $qty, $description) {
            $case = MetalSettlementCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($case);

            $wallet = $this->lockMetalWallet((int) $case->metal_trader_id, (int) $case->spot_metal_item_id);
            $before = $this->decimal($wallet->available_balance ?? '0', self::METAL_SCALE);
            $after = bcadd($before, $qty, self::METAL_SCALE);

            $wallet->forceFill(['available_balance' => $after])->save();

            $tx = WalletTransaction::create([
                'metal_trader_wallet_id' => $wallet->id,
                'metal_order_id' => null,
                'metal_settlement_case_id' => $case->id,
                'amount' => $this->decimal($qty, self::METAL_SCALE),
                'type' => 'settlement_delivery_in',
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $description ?: sprintf('تحویل فلز تسویه تعهد سررسید #%s', $case->id),
            ]);

            $this->autoFulfillIfCleared($case);

            return $tx->fresh();
        }, 3);
    }

    public function fulfillManually(MetalSettlementCase $case, ?string $notes = null): MetalSettlementCase
    {
        $this->assertOpen($case);

        return DB::transaction(function () use ($case, $notes) {
            $case = MetalSettlementCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($case);

            $case->forceFill([
                'status' => MetalSettlementCaseStatus::FULFILLED,
                'fulfilled_at' => now(),
                'fulfilled_by' => Auth::id(),
                'notes' => $notes ?? $case->notes,
            ])->save();

            return $case->fresh(['orders', 'trader', 'commitmentMetalItem', 'spotMetalItem']);
        }, 3);
    }

    public function autoFulfillIfCleared(MetalSettlementCase $case): ?MetalSettlementCase
    {
        $case = $case->fresh();
        if (! $case || ! $case->isOpen()) {
            return $case;
        }

        $cash = $this->decimal(
            $this->getOrCreateCashWallet((int) $case->metal_trader_id)->available_balance ?? '0',
            self::FIAT_SCALE
        );
        $metal = $this->decimal(
            $this->getOrCreateMetalWallet((int) $case->metal_trader_id, (int) $case->spot_metal_item_id)->available_balance ?? '0',
            self::METAL_SCALE
        );

        $needsFiat = in_array($case->obligation_side, [
            MetalSettlementObligationSide::FIAT,
            MetalSettlementObligationSide::BOTH,
        ], true);
        $needsMetal = in_array($case->obligation_side, [
            MetalSettlementObligationSide::METAL,
            MetalSettlementObligationSide::BOTH,
        ], true);

        $fiatOk = ! $needsFiat || bccomp($cash, '0', self::FIAT_SCALE) >= 0;
        $metalOk = ! $needsMetal || bccomp($metal, '0', self::METAL_SCALE) >= 0;

        if (! $fiatOk || ! $metalOk) {
            return $case;
        }

        $case->forceFill([
            'status' => MetalSettlementCaseStatus::FULFILLED,
            'fulfilled_at' => now(),
            'fulfilled_by' => Auth::id(),
            'notes' => trim(($case->notes ? $case->notes . "\n" : '') . 'تکمیل خودکار پس از تسویه کامل کیف'),
        ])->save();

        return $case->fresh();
    }

    /**
     * پیش‌نمایش نکول: ضرر جایگزین پیشنهادی + ۱٪ ارزش سفارش‌های مرتبط.
     *
     * @return array{
     *   one_percent: string,
     *   suggested_replacement_loss: string,
     *   suggested_total: string,
     *   original_notional: string,
     *   market_notional: string,
     *   side: string,
     *   qty: string
     * }
     */
    public function previewDefault(MetalSettlementCase $case): array
    {
        $this->assertOpen($case);
        $case->loadMissing('orders');

        $isBuyDefault = bccomp((string) $case->net_metal, '0', self::METAL_SCALE) > 0
            || $case->obligation_side === MetalSettlementObligationSide::FIAT;

        if ($isBuyDefault) {
            $orders = $case->orders->where('order_type', 'buy');
            $qty = $this->sumQty($orders);
            $original = $this->sumAmount($orders);
            $marketUnit = $this->latestUnitPrice((int) $case->spot_metal_item_id, 'buy');
            $marketNotional = $this->mulMoney($qty, $marketUnit);
            // ضرر فروش جایگزین: اگر الان ارزان‌تر از خرید اولیه بفروشیم
            $loss = bcsub($original, $marketNotional, self::FIAT_SCALE);
            if (bccomp($loss, '0', self::FIAT_SCALE) < 0) {
                $loss = '0';
            }
        } else {
            $orders = $case->orders->where('order_type', 'sell');
            $qty = $this->sumQty($orders);
            $original = $this->sumAmount($orders);
            $marketUnit = $this->latestUnitPrice((int) $case->spot_metal_item_id, 'sell');
            $marketNotional = $this->mulMoney($qty, $marketUnit);
            // ضرر خرید جایگزین: اگر الان گران‌تر از فروش اولیه بخریم
            $loss = bcsub($marketNotional, $original, self::FIAT_SCALE);
            if (bccomp($loss, '0', self::FIAT_SCALE) < 0) {
                $loss = '0';
            }
        }

        $onePercent = $this->percentOf($original, self::PENALTY_PERCENT);
        $total = bcadd($loss, $onePercent, self::FIAT_SCALE);

        return [
            'one_percent' => $onePercent,
            'suggested_replacement_loss' => $loss,
            'suggested_total' => $total,
            'original_notional' => $original,
            'market_notional' => $marketNotional,
            'market_unit_price' => $marketUnit,
            'side' => $isBuyDefault ? 'buy' : 'sell',
            'qty' => $qty,
        ];
    }

    /**
     * اجرای نکول با اصلاحیه سفارش‌ها و کسر ضرر+۱٪ از کیف نقد.
     */
    public function executeDefault(
        MetalSettlementCase $case,
        string $replacementLoss,
        bool $partnerTradeUsed = false,
        ?string $notes = null,
        ?array $partnerMeta = null,
    ): MetalSettlementCase {
        $this->assertOpen($case);

        if (bccomp($replacementLoss, '0', self::FIAT_SCALE) < 0) {
            throw new LogicException('مقدار ضرر نمی‌تواند منفی باشد.');
        }

        return DB::transaction(function () use ($case, $replacementLoss, $partnerTradeUsed, $notes, $partnerMeta) {
            $case = MetalSettlementCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($case);
            $case->load('orders');

            $preview = $this->previewDefault($case);
            $onePercent = $preview['one_percent'];
            $penaltyTotal = bcadd($replacementLoss, $onePercent, self::FIAT_SCALE);
            $isBuyDefault = $preview['side'] === 'buy';

            $cashWallet = $this->lockCashWallet((int) $case->metal_trader_id);
            $spotWallet = $this->lockMetalWallet((int) $case->metal_trader_id, (int) $case->spot_metal_item_id);

            if ($isBuyDefault) {
                $this->applyBuyDefaultCorrections($case, $spotWallet, $cashWallet);
            } else {
                $this->applySellDefaultCorrections($case, $spotWallet, $cashWallet);
            }

            // کسر مجموع ضرر + ۱٪ از کیف تومانی T0
            $cashBefore = $this->decimal($cashWallet->fresh()->available_balance ?? '0', self::FIAT_SCALE);
            $cashAfter = bcsub($cashBefore, $penaltyTotal, self::FIAT_SCALE);
            $cashWallet->forceFill(['available_balance' => $cashAfter])->save();

            WalletTransaction::create([
                'metal_trader_wallet_id' => $cashWallet->id,
                'metal_order_id' => null,
                'metal_settlement_case_id' => $case->id,
                'amount' => $penaltyTotal,
                'type' => 'penalty_loss',
                'balance_before' => $cashBefore,
                'balance_after' => $cashAfter,
                'description' => sprintf(
                    'کسر ضرر جایگزین (%s) + ۱٪ ارزش سفارش (%s) برای نکول پرونده #%s',
                    number_format((float) $replacementLoss, 0, '', ','),
                    number_format((float) $onePercent, 0, '', ','),
                    $case->id
                ),
            ]);

            $extra = $case->extra_data ?? [];
            $extra['default_preview'] = $preview;
            if ($partnerMeta) {
                $extra['partner_trade'] = $partnerMeta;
            }

            $case->forceFill([
                'status' => MetalSettlementCaseStatus::DEFAULTED,
                'defaulted_at' => now(),
                'defaulted_by' => Auth::id(),
                'penalty_one_percent' => $onePercent,
                'replacement_loss' => $this->decimal($replacementLoss, self::FIAT_SCALE),
                'penalty_total' => $penaltyTotal,
                'partner_trade_used' => $partnerTradeUsed,
                'extra_data' => $extra,
                'notes' => $notes ?? $case->notes,
            ])->save();

            return $case->fresh(['orders', 'trader', 'commitmentMetalItem', 'spotMetalItem', 'walletTransactions']);
        }, 3);
    }

    /**
     * فروش فرضی/بازار برای فلز باقی‌مانده خرید نکول‌شده؛ مقدار ضرر را برمی‌گرداند.
     * اتصال واقعی همکار در کنترلر انجام می‌شود و نتیجه اینجا فقط قیمت‌گذاری می‌شود.
     */
    public function estimatePartnerSellProceeds(MetalSettlementCase $case): array
    {
        $preview = $this->previewDefault($case);
        if ($preview['side'] !== 'buy') {
            throw new LogicException('فروش همکار فقط برای نکول سمت خرید معنا دارد.');
        }

        $unit = $this->latestUnitPrice((int) $case->spot_metal_item_id, 'buy');
        $proceeds = $this->mulMoney($preview['qty'], $unit);
        $loss = bcsub($preview['original_notional'], $proceeds, self::FIAT_SCALE);
        if (bccomp($loss, '0', self::FIAT_SCALE) < 0) {
            $loss = '0';
        }

        return [
            'qty' => $preview['qty'],
            'unit_price' => $unit,
            'proceeds' => $proceeds,
            'replacement_loss' => $loss,
            'one_percent' => $preview['one_percent'],
            'total' => bcadd($loss, $preview['one_percent'], self::FIAT_SCALE),
        ];
    }

    public function estimatePartnerBuyCost(MetalSettlementCase $case): array
    {
        $preview = $this->previewDefault($case);
        if ($preview['side'] !== 'sell') {
            throw new LogicException('خرید همکار فقط برای نکول سمت فروش معنا دارد.');
        }

        $unit = $this->latestUnitPrice((int) $case->spot_metal_item_id, 'sell');
        $cost = $this->mulMoney($preview['qty'], $unit);
        $loss = bcsub($cost, $preview['original_notional'], self::FIAT_SCALE);
        if (bccomp($loss, '0', self::FIAT_SCALE) < 0) {
            $loss = '0';
        }

        return [
            'qty' => $preview['qty'],
            'unit_price' => $unit,
            'cost' => $cost,
            'replacement_loss' => $loss,
            'one_percent' => $preview['one_percent'],
            'total' => bcadd($loss, $preview['one_percent'], self::FIAT_SCALE),
        ];
    }

    private function applyBuyDefaultCorrections(
        MetalSettlementCase $case,
        MetalTraderWallet $spotWallet,
        MetalTraderWallet $cashWallet
    ): void {
        foreach ($case->orders->where('order_type', 'buy') as $order) {
            /** @var MetalOrder $order */
            $qty = $this->decimal((string) $order->qty, self::METAL_SCALE);
            $amount = $this->decimal((string) $order->amount, self::FIAT_SCALE);

            $metalBefore = $this->decimal($spotWallet->available_balance ?? '0', self::METAL_SCALE);
            $metalAfter = bcsub($metalBefore, $qty, self::METAL_SCALE);
            $spotWallet->forceFill(['available_balance' => $metalAfter])->save();

            $metalTx = WalletTransaction::create([
                'metal_trader_wallet_id' => $spotWallet->id,
                'metal_order_id' => $order->id,
                'metal_settlement_case_id' => $case->id,
                'amount' => $qty,
                'type' => 'settlement_correction_metal',
                'balance_before' => $metalBefore,
                'balance_after' => $metalAfter,
                'description' => sprintf('اصلاحیه نکول: کسر %s فلز سفارش خرید #%s', $qty, $order->id),
            ]);

            $cashBefore = $this->decimal($cashWallet->available_balance ?? '0', self::FIAT_SCALE);
            $cashAfter = bcadd($cashBefore, $amount, self::FIAT_SCALE);
            $cashWallet->forceFill(['available_balance' => $cashAfter])->save();

            WalletTransaction::create([
                'metal_trader_wallet_id' => $cashWallet->id,
                'metal_order_id' => $order->id,
                'metal_settlement_case_id' => $case->id,
                'related_transaction_id' => $metalTx->id,
                'amount' => $amount,
                'type' => 'settlement_correction_fiat',
                'balance_before' => $cashBefore,
                'balance_after' => $cashAfter,
                'description' => sprintf('اصلاحیه نکول: برگشت %s تومان سفارش خرید #%s', $amount, $order->id),
            ]);
        }
    }

    private function applySellDefaultCorrections(
        MetalSettlementCase $case,
        MetalTraderWallet $spotWallet,
        MetalTraderWallet $cashWallet
    ): void {
        foreach ($case->orders->where('order_type', 'sell') as $order) {
            /** @var MetalOrder $order */
            $qty = $this->decimal((string) $order->qty, self::METAL_SCALE);
            $amount = $this->decimal((string) $order->amount, self::FIAT_SCALE);

            // Short را می‌بندیم: فلز به کیف برمی‌گردد (از منفی به سمت صفر)
            $metalBefore = $this->decimal($spotWallet->available_balance ?? '0', self::METAL_SCALE);
            $metalAfter = bcadd($metalBefore, $qty, self::METAL_SCALE);
            $spotWallet->forceFill(['available_balance' => $metalAfter])->save();

            $metalTx = WalletTransaction::create([
                'metal_trader_wallet_id' => $spotWallet->id,
                'metal_order_id' => $order->id,
                'metal_settlement_case_id' => $case->id,
                'amount' => $qty,
                'type' => 'settlement_correction_metal',
                'balance_before' => $metalBefore,
                'balance_after' => $metalAfter,
                'description' => sprintf('اصلاحیه نکول: برگشت %s فلز سفارش فروش #%s', $qty, $order->id),
            ]);

            $cashBefore = $this->decimal($cashWallet->available_balance ?? '0', self::FIAT_SCALE);
            $cashAfter = bcsub($cashBefore, $amount, self::FIAT_SCALE);
            $cashWallet->forceFill(['available_balance' => $cashAfter])->save();

            WalletTransaction::create([
                'metal_trader_wallet_id' => $cashWallet->id,
                'metal_order_id' => $order->id,
                'metal_settlement_case_id' => $case->id,
                'related_transaction_id' => $metalTx->id,
                'amount' => $amount,
                'type' => 'settlement_correction_fiat',
                'balance_before' => $cashBefore,
                'balance_after' => $cashAfter,
                'description' => sprintf('اصلاحیه نکول: کسر %s تومان سفارش فروش #%s', $amount, $order->id),
            ]);
        }
    }

    private function assertOpen(MetalSettlementCase $case): void
    {
        if (! $case->isOpen()) {
            throw new RuntimeException('این پرونده سررسید باز نیست.');
        }
    }

    private function sumQty(Collection $orders): string
    {
        $sum = '0';
        foreach ($orders as $order) {
            $sum = bcadd($sum, $this->decimal((string) $order->qty, self::METAL_SCALE), self::METAL_SCALE);
        }

        return $sum;
    }

    private function sumAmount(Collection $orders): string
    {
        $sum = '0';
        foreach ($orders as $order) {
            $sum = bcadd($sum, $this->decimal((string) $order->amount, self::FIAT_SCALE), self::FIAT_SCALE);
        }

        return $sum;
    }

    private function percentOf(string $amount, string $percent): string
    {
        $raw = bcmul($amount, bcdiv($percent, '100', 8), 8);

        return bcadd($raw, '0', self::FIAT_SCALE);
    }

    private function mulMoney(string $qty, string $unitPrice): string
    {
        return bcadd(bcmul($qty, $unitPrice, 4), '0', self::FIAT_SCALE);
    }

    private function latestUnitPrice(int $metalItemId, string $side): string
    {
        $price = SelectedMetalPrice::query()
            ->where('metal_item_id', $metalItemId)
            ->latest('id')
            ->first();

        if (! $price) {
            return '0';
        }

        // برای فروش جایگزین فلز خریداری‌شده از نرخ buy بازار استفاده می‌کنیم (قیمتی که بازار از ما می‌خرد)
        // برای خرید جایگزین فلز فروخته‌شده از نرخ sell بازار استفاده می‌کنیم
        $value = $side === 'buy'
            ? ($price->buy ?? $price->sell ?? 0)
            : ($price->sell ?? $price->buy ?? 0);

        return $this->decimal((string) $value, self::FIAT_SCALE);
    }

    private function getOrCreateCashWallet(int $traderId): MetalTraderWallet
    {
        $wallet = MetalTraderWallet::query()
            ->where('metal_trader_id', $traderId)
            ->whereNull('metal_item_id')
            ->first();

        if ($wallet) {
            return $wallet;
        }

        try {
            return MetalTraderWallet::query()->create([
                'metal_trader_id' => $traderId,
                'metal_item_id' => null,
                'available_balance' => '0',
                'locked_balance' => '0',
                'blocked_balance' => '0',
                'fiat_balance' => '0',
                'avg_buy_price' => '0',
            ]);
        } catch (\Illuminate\Database\QueryException) {
            return MetalTraderWallet::query()
                ->where('metal_trader_id', $traderId)
                ->whereNull('metal_item_id')
                ->firstOrFail();
        }
    }

    private function getOrCreateMetalWallet(int $traderId, int $metalItemId): MetalTraderWallet
    {
        $wallet = MetalTraderWallet::query()
            ->where('metal_trader_id', $traderId)
            ->where('metal_item_id', $metalItemId)
            ->first();

        if ($wallet) {
            return $wallet;
        }

        try {
            return MetalTraderWallet::query()->create([
                'metal_trader_id' => $traderId,
                'metal_item_id' => $metalItemId,
                'available_balance' => '0',
                'locked_balance' => '0',
                'blocked_balance' => '0',
                'fiat_balance' => '0',
                'avg_buy_price' => '0',
            ]);
        } catch (\Illuminate\Database\QueryException) {
            return MetalTraderWallet::query()
                ->where('metal_trader_id', $traderId)
                ->where('metal_item_id', $metalItemId)
                ->firstOrFail();
        }
    }

    private function lockCashWallet(int $traderId): MetalTraderWallet
    {
        $wallet = $this->getOrCreateCashWallet($traderId);

        return MetalTraderWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
    }

    private function lockMetalWallet(int $traderId, int $metalItemId): MetalTraderWallet
    {
        $wallet = $this->getOrCreateMetalWallet($traderId, $metalItemId);

        return MetalTraderWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
    }

    private function decimal(string $value, int $scale): string
    {
        if (! is_numeric($value)) {
            throw new LogicException('مقدار عددی نامعتبر است.');
        }

        return bcadd($value, '0', $scale);
    }
}
