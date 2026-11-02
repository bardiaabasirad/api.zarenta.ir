<?php

namespace App\Services\Metal;

use App\Constants\AppConstants;
use App\Models\MetalItem;
use App\Models\MetalOrder;
use App\Models\MetalTrader;
use App\Models\MetalTraderFiatWithdrawal;
use App\Models\MetalTraderWallet;
use App\Models\SelectedMetalPrice;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class MetalOrderAssetRiskService
{
    public const DEFAULT_THRESHOLD = 10.0;

    /**
     * ارزیابی نسبت ارزش دارایی‌های معامله‌گر به مبلغ سفارش.
     *
     * @return array{
     *     total_assets_toman: float,
     *     order_amount: float,
     *     asset_ratio_percent: float|null,
     *     threshold_percent: float,
     *     shortage_toman: float,
     *     is_sufficient: bool
     * }|null
     */
    public function assess(MetalOrder $order, ?MetalTrader $trader = null, ?array $priceMap = null): ?array
    {
        if ($order->created_type !== 'metal_trader') {
            return null;
        }

        $trader ??= $order->relationLoaded('creator') && $order->creator instanceof MetalTrader
            ? $order->creator
            : MetalTrader::query()->find($order->created_id);

        if (!$trader) {
            return null;
        }

        $orderAmount = (float) ($order->amount ?? 0);
        $threshold = $this->resolveThreshold($trader);
        $totalAssets = $this->totalAssetsToman((int) $trader->id, $priceMap);

        $ratio = null;
        if ($orderAmount > 0) {
            $ratio = round(($totalAssets / $orderAmount) * 100, 2);
        }

        $requiredAssets = $orderAmount > 0
            ? ($orderAmount * $threshold / 100)
            : 0.0;

        $shortage = max(0, round($requiredAssets - $totalAssets, 0));

        $isSufficient = $threshold <= 0 || $orderAmount <= 0 || $totalAssets >= $requiredAssets;

        return [
            'total_assets_toman' => round($totalAssets, 0),
            'order_amount' => round($orderAmount, 0),
            'asset_ratio_percent' => $ratio,
            'threshold_percent' => $threshold,
            'shortage_toman' => $shortage,
            'is_sufficient' => $isSufficient,
        ];
    }

    /**
     * ضمیمه‌کردن نتیجه ارزیابی دارایی به مجموعه‌ای از سفارش‌ها (برای mazane / broadcast).
     */
    public function attachToOrders(Collection $orders): Collection
    {
        $traderOrders = $orders->filter(
            fn (MetalOrder $order) => $order->created_type === 'metal_trader'
        );

        if ($traderOrders->isEmpty()) {
            return $orders;
        }

        $traderIds = $traderOrders->pluck('created_id')->unique()->values();

        $traders = MetalTrader::query()
            ->whereIn('id', $traderIds)
            ->get(['id', 'asset_risk_threshold'])
            ->keyBy('id');

        $priceMap = $this->buildPriceMap();

        return $orders->map(function (MetalOrder $order) use ($traders, $priceMap) {
            if ($order->created_type !== 'metal_trader') {
                $order->setAttribute('asset_risk', null);

                return $order;
            }

            $trader = $traders->get($order->created_id);
            $order->setAttribute('asset_risk', $this->assess($order, $trader, $priceMap));

            return $order;
        });
    }

    public function resolveThreshold(?MetalTrader $trader): float
    {
        if (!$trader) {
            return self::DEFAULT_THRESHOLD;
        }

        $value = $trader->asset_risk_threshold;

        if ($value === null || $value === '') {
            return self::DEFAULT_THRESHOLD;
        }

        return max(0, (float) $value);
    }

    /**
     * ارزیابی اثر پرداخت برداشت روی پوشش ریسک معاملات تعهدی تسویه‌نشده.
     *
     * توجه: مبلغ درخواست‌های pending از کیف تومان کسر شده است؛
     * بنابراین ارزش فعلی دارایی‌ها همان وضعیت «پس از پرداخت» است.
     *
     * @return array{
     *     withdrawal_amount: float,
     *     total_assets_after_withdrawal: float,
     *     fiat_available_after_withdrawal: float,
     *     open_commitment_amount: float,
     *     open_commitment_orders_count: int,
     *     threshold_percent: float,
     *     required_reserve_toman: float,
     *     asset_ratio_percent: float|null,
     *     shortage_toman: float,
     *     covers_reserve: bool
     * }
     */
    public function assessWithdrawal(
        MetalTrader $trader,
        float|int|string $withdrawalAmount,
        ?array $priceMap = null,
        ?float $totalAssetsOverride = null,
        ?float $fiatAvailableOverride = null,
        ?float $openCommitmentAmountOverride = null,
        ?int $openCommitmentCountOverride = null
    ): array {
        $priceMap ??= $this->buildPriceMap();
        $withdrawalAmount = (float) $withdrawalAmount;
        $threshold = $this->resolveThreshold($trader);

        $totalAssetsAfter = $totalAssetsOverride ?? $this->totalAssetsToman((int) $trader->id, $priceMap);
        $fiatAvailableAfter = $fiatAvailableOverride ?? $this->fiatAvailableToman((int) $trader->id);

        $openCommitmentAmount = $openCommitmentAmountOverride
            ?? $this->openCommitmentAmount((int) $trader->id);
        $openCommitmentCount = $openCommitmentCountOverride
            ?? $this->openCommitmentOrdersCount((int) $trader->id);

        $requiredReserve = $openCommitmentAmount > 0
            ? ($openCommitmentAmount * $threshold / 100)
            : 0.0;

        $ratio = null;
        if ($openCommitmentAmount > 0) {
            $ratio = round(($totalAssetsAfter / $openCommitmentAmount) * 100, 2);
        }

        $shortage = max(0, round($requiredReserve - $totalAssetsAfter, 0));
        $coversReserve = $threshold <= 0
            || $openCommitmentAmount <= 0
            || $totalAssetsAfter >= $requiredReserve;

        return [
            'withdrawal_amount' => round($withdrawalAmount, 0),
            'total_assets_after_withdrawal' => round($totalAssetsAfter, 0),
            'fiat_available_after_withdrawal' => round($fiatAvailableAfter, 0),
            'open_commitment_amount' => round($openCommitmentAmount, 0),
            'open_commitment_orders_count' => $openCommitmentCount,
            'threshold_percent' => $threshold,
            'required_reserve_toman' => round($requiredReserve, 0),
            'asset_ratio_percent' => $ratio,
            'shortage_toman' => $shortage,
            'covers_reserve' => $coversReserve,
        ];
    }

    /**
     * ضمیمه ارزیابی ریسک برداشت به لیست درخواست‌های pending.
     */
    public function attachToWithdrawals(Collection $withdrawals): Collection
    {
        $pending = $withdrawals->filter(
            fn ($withdrawal) => $withdrawal instanceof MetalTraderFiatWithdrawal
                && $withdrawal->status === MetalTraderFiatWithdrawal::STATUS_PENDING
        );

        if ($pending->isEmpty()) {
            return $withdrawals->map(function ($withdrawal) {
                if ($withdrawal instanceof MetalTraderFiatWithdrawal) {
                    $withdrawal->setAttribute('withdrawal_risk', null);
                }

                return $withdrawal;
            });
        }

        $traderIds = $pending->pluck('metal_trader_id')->unique()->values();
        $traders = MetalTrader::query()
            ->whereIn('id', $traderIds)
            ->get(['id', 'asset_risk_threshold'])
            ->keyBy('id');

        $priceMap = $this->buildPriceMap();
        $assetsByTrader = [];
        $fiatByTrader = [];
        $commitmentByTrader = $this->openCommitmentAmountsByTrader($traderIds->all());
        $commitmentCountByTrader = $this->openCommitmentCountsByTrader($traderIds->all());

        foreach ($traderIds as $traderId) {
            $assetsByTrader[$traderId] = $this->totalAssetsToman((int) $traderId, $priceMap);
            $fiatByTrader[$traderId] = $this->fiatAvailableToman((int) $traderId);
        }

        return $withdrawals->map(function ($withdrawal) use (
            $traders,
            $assetsByTrader,
            $fiatByTrader,
            $commitmentByTrader,
            $commitmentCountByTrader
        ) {
            if (! $withdrawal instanceof MetalTraderFiatWithdrawal) {
                return $withdrawal;
            }

            if ($withdrawal->status !== MetalTraderFiatWithdrawal::STATUS_PENDING) {
                $withdrawal->setAttribute('withdrawal_risk', null);

                return $withdrawal;
            }

            $trader = $traders->get($withdrawal->metal_trader_id);
            if (! $trader) {
                $withdrawal->setAttribute('withdrawal_risk', null);

                return $withdrawal;
            }

            $traderId = (int) $withdrawal->metal_trader_id;
            $withdrawal->setAttribute(
                'withdrawal_risk',
                $this->assessWithdrawal(
                    $trader,
                    (float) $withdrawal->amount,
                    null,
                    $assetsByTrader[$traderId] ?? 0.0,
                    $fiatByTrader[$traderId] ?? 0.0,
                    $commitmentByTrader[$traderId] ?? 0.0,
                    $commitmentCountByTrader[$traderId] ?? 0
                )
            );

            return $withdrawal;
        });
    }

    /**
     * مجموع مبلغ سفارش‌های تعهدی تاییدشده و تسویه‌نشده کاربر.
     */
    public function openCommitmentAmount(int $traderId): float
    {
        return (float) MetalOrder::query()
            ->where('created_type', 'metal_trader')
            ->where('created_id', $traderId)
            ->where('status', 'succeed')
            ->where('settlement_status', 'pending')
            ->sum('amount');
    }

    public function openCommitmentOrdersCount(int $traderId): int
    {
        return (int) MetalOrder::query()
            ->where('created_type', 'metal_trader')
            ->where('created_id', $traderId)
            ->where('status', 'succeed')
            ->where('settlement_status', 'pending')
            ->count();
    }

    /**
     * @param  array<int>  $traderIds
     * @return array<int, float>
     */
    public function openCommitmentAmountsByTrader(array $traderIds): array
    {
        if ($traderIds === []) {
            return [];
        }

        return MetalOrder::query()
            ->selectRaw('created_id, COALESCE(SUM(amount), 0) as total_amount')
            ->where('created_type', 'metal_trader')
            ->whereIn('created_id', $traderIds)
            ->where('status', 'succeed')
            ->where('settlement_status', 'pending')
            ->groupBy('created_id')
            ->pluck('total_amount', 'created_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @param  array<int>  $traderIds
     * @return array<int, int>
     */
    public function openCommitmentCountsByTrader(array $traderIds): array
    {
        if ($traderIds === []) {
            return [];
        }

        return MetalOrder::query()
            ->selectRaw('created_id, COUNT(*) as orders_count')
            ->where('created_type', 'metal_trader')
            ->whereIn('created_id', $traderIds)
            ->where('status', 'succeed')
            ->where('settlement_status', 'pending')
            ->groupBy('created_id')
            ->pluck('orders_count', 'created_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function fiatAvailableToman(int $traderId): float
    {
        $wallet = MetalTraderWallet::query()
            ->where('metal_trader_id', $traderId)
            ->whereNull('metal_item_id')
            ->first();

        return (float) ($wallet->available_balance ?? 0);
    }

    /**
     * ارزش مجموع دارایی‌های کاربر در تمام کیف‌پول‌ها به تومان.
     */
    public function totalAssetsToman(int $traderId, ?array $priceMap = null): float
    {
        $priceMap ??= $this->buildPriceMap();

        $wallets = MetalTraderWallet::query()
            ->where('metal_trader_id', $traderId)
            ->with([
                'metalItem' => function ($query) {
                    $query->withoutGlobalScopes()
                        ->select(['id', 'unit', 'is_spot']);
                },
            ])
            ->get();

        $total = 0.0;

        foreach ($wallets as $wallet) {
            if ($wallet->metal_item_id === null) {
                $total += (float) ($wallet->available_balance ?? 0);
                continue;
            }

            $metalQty = (float) ($wallet->available_balance ?? 0)
                + (float) ($wallet->blocked_balance ?? 0);

            $unitPrice = $this->unitPriceInToman(
                (int) $wallet->metal_item_id,
                $wallet->metalItem,
                $priceMap
            );

            if ($unitPrice !== null) {
                $total += $metalQty * $unitPrice;
            }

            $total += (float) ($wallet->fiat_balance ?? 0);
        }

        return $total;
    }

    /**
     * ساخت نقشه قیمت‌های معتبر (منطق هم‌تراز با AssetController).
     *
     * @return array<int, array{buy: float, best_spot_buy: float|null, unit: string|null, is_spot: bool}>
     */
    public function buildPriceMap(): array
    {
        $since = now()->subDay()->startOfDay();

        $latestByItem = SelectedMetalPrice::query()
            ->with([
                'metalItem' => function ($query) {
                    $query->withoutGlobalScopes()
                        ->select('id', 'unit', 'is_spot', 'settlement_metal_item_id');
                },
            ])
            ->whereIn('id', function (Builder $query) use ($since) {
                $query->selectRaw('MAX(id)')
                    ->from('selected_metal_prices')
                    ->where('created_at', '>=', $since)
                    ->groupBy('metal_item_id');
            })
            ->get();

        $spotAggregates = SelectedMetalPrice::query()
            ->join('metal_items', 'selected_metal_prices.metal_item_id', '=', 'metal_items.id')
            ->whereIn('selected_metal_prices.id', function (Builder $query) use ($since) {
                $query->selectRaw('MAX(id)')
                    ->from('selected_metal_prices')
                    ->where('created_at', '>=', $since)
                    ->groupBy('metal_item_id');
            })
            ->whereNotNull('metal_items.settlement_metal_item_id')
            ->groupBy('metal_items.settlement_metal_item_id')
            ->selectRaw('
                metal_items.settlement_metal_item_id,
                MAX(selected_metal_prices.buy) as max_buy,
                MIN(selected_metal_prices.sell) as min_sell
            ')
            ->get()
            ->keyBy('settlement_metal_item_id');

        $spotItems = MetalItem::query()
            ->withoutGlobalScopes()
            ->where('is_spot', true)
            ->get(['id', 'unit', 'is_spot']);

        $map = [];

        foreach ($latestByItem as $price) {
            $item = $price->metalItem;
            if ($item?->is_spot) {
                continue;
            }

            $map[(int) $price->metal_item_id] = [
                'buy' => (float) ($price->buy ?? 0),
                'best_spot_buy' => null,
                'unit' => $item?->unit,
                'is_spot' => false,
            ];
        }

        foreach ($spotItems as $item) {
            $agg = $spotAggregates->get($item->id);
            $maxBuy = $agg ? (float) $agg->max_buy : 0.0;

            $map[(int) $item->id] = [
                'buy' => $maxBuy,
                'best_spot_buy' => $maxBuy,
                'unit' => $item->unit,
                'is_spot' => true,
            ];
        }

        return $map;
    }

    private function unitPriceInToman(int $metalItemId, $metalItem, array $priceMap): ?float
    {
        $price = $priceMap[$metalItemId] ?? null;
        if (!$price) {
            return null;
        }

        $isSpot = (bool) ($metalItem?->is_spot || $price['is_spot']);
        $rawBuy = $isSpot && $price['best_spot_buy'] !== null
            ? (float) $price['best_spot_buy']
            : (float) $price['buy'];

        if (!is_finite($rawBuy) || $rawBuy <= 0) {
            return null;
        }

        $unit = $metalItem?->unit ?? $price['unit'] ?? null;
        if ($unit === 'gram') {
            return $rawBuy / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR;
        }

        return $rawBuy;
    }
}
