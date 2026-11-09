<?php

namespace App\Services;

use App\Models\MetalOrder;
use App\Models\MetalTraderWallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class AssetService
{
    private const METAL_SCALE = 4;
    private const FIAT_SCALE = 0;
    private const PRICE_SCALE = 0;

    public static function updateAsset(MetalOrder $metalOrder): void
    {
        if ($metalOrder->created_type !== 'metal_trader') {
            return;
        }

        DB::transaction(function () use ($metalOrder): void {
            $order = MetalOrder::query()
                ->whereKey($metalOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->created_type !== 'metal_trader') {
                return;
            }

            if ($order->metal_item_id === null) {
                throw new LogicException('برای ثبت معاملهٔ فلزی، metal_item_id نباید null باشد.');
            }

            if (! in_array($order->order_type, ['buy', 'sell'], true)) {
                throw new LogicException('نوع سفارش باید buy یا sell باشد.');
            }

            $isBuy = $order->order_type === 'buy';

            $metalType = $isBuy
                ? 'metal_commitment_in'
                : 'metal_commitment_out';

            $fiatType = $isBuy
                ? 'fiat_debt_in'
                : 'fiat_credit_in';

            // بررسی Idempotency
            $existingTypes = WalletTransaction::query()
                ->where('metal_order_id', $order->getKey())
                ->whereIn('type', [$metalType, $fiatType])
                ->distinct()
                ->pluck('type')
                ->all();

            if (count($existingTypes) === 2) {
                return;
            }

            if (count($existingTypes) !== 0) {
                throw new RuntimeException('برای این سفارش فقط بخشی از تراکنش‌های دفتر کل وجود دارد.');
            }

            $quantity = self::decimal(
                $order->qty,
                self::METAL_SCALE,
                'quantity'
            );

            $fiatAmount = self::decimal(
                $order->amount,
                self::FIAT_SCALE,
                'amount'
            );

            if (bccomp($quantity, '0', self::METAL_SCALE) <= 0) {
                throw new LogicException('مقدار فلز باید بزرگ‌تر از صفر باشد.');
            }

            if (bccomp($fiatAmount, '0', self::FIAT_SCALE) <= 0) {
                throw new LogicException('مبلغ معامله باید بزرگ‌تر از صفر باشد.');
            }

            $metalDelta = $isBuy
                ? $quantity
                : bcsub('0', $quantity, self::METAL_SCALE);

            $fiatDelta = $isBuy
                ? bcsub('0', $fiatAmount, self::FIAT_SCALE)
                : $fiatAmount;

            $order->loadMissing([
                'metalItem' => static fn ($query) => $query->withoutGlobalScopes(),
            ]);

            // نقد حاضر (T0/spot): تومان فوراً روی کیف نقدی (metal_item_id = null)
            // تعهدی (T1/T2): تومان روی fiat_balance همان کیف فلز می‌ماند تا تسویه
            $isSpot = (bool) ($order->metalItem?->is_spot);

            $wallet = MetalTraderWallet::query()->firstOrCreate(
                [
                    'metal_trader_id' => $order->created_id,
                    'metal_item_id'   => $order->metal_item_id,
                ],
                [
                    'available_balance' => '0',
                    'blocked_balance'   => '0',
                    'fiat_balance'      => '0',
                    'avg_buy_price'     => '0',
                ]
            );

            $wallet = MetalTraderWallet::query()
                ->whereKey($wallet->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $metalBefore = self::decimal($wallet->available_balance ?? '0', self::METAL_SCALE, 'available_balance');
            $avgBefore   = self::decimal($wallet->avg_buy_price ?? '0', self::PRICE_SCALE, 'avg_buy_price');

            $metalAfter = bcadd($metalBefore, $metalDelta, self::METAL_SCALE);

            // محاسبه قیمت واحد سفارش فعلی (مثلاً ۱۸,۵۶۰,۴۰۵)
            $orderUnitPrice = self::bcdivRound($fiatAmount, $quantity, self::PRICE_SCALE);

            // -----------------------------------------------------------------
            // محاسبه هوشمند میانگین پوزیشن (دو طرفه: مثبت Long و منفی Short)
            // -----------------------------------------------------------------
            $avgAfter = self::calculateNewAverage(
                metalBefore: $metalBefore,
                avgBefore: $avgBefore,
                metalAfter: $metalAfter,
                quantity: $quantity,
                fiatAmount: $fiatAmount,
                orderUnitPrice: $orderUnitPrice,
                isBuy: $isBuy
            );

            if ($isSpot) {
                $cashWallet = self::getOrCreateCashWallet((int) $order->created_id);
                $cashWallet = MetalTraderWallet::query()
                    ->whereKey($cashWallet->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $cashBefore = self::decimal(
                    $cashWallet->available_balance ?? '0',
                    self::FIAT_SCALE,
                    'available_balance'
                );
                $cashAfter = bcadd($cashBefore, $fiatDelta, self::FIAT_SCALE);

                $wallet->forceFill([
                    'available_balance' => $metalAfter,
                    'avg_buy_price'     => $avgAfter,
                ])->save();

                $cashWallet->forceFill([
                    'available_balance' => $cashAfter,
                ])->save();

                // نقد حاضر همان لحظه تسویه شده است؛ نباید وارد مسیر تسویه تعهدی شود
                if ($order->settlement_status !== 'settled') {
                    $order->forceFill(['settlement_status' => 'settled'])->save();
                }

                $metalTransaction = WalletTransaction::create([
                    'metal_trader_wallet_id' => $wallet->getKey(),
                    'metal_order_id'         => $order->getKey(),
                    'related_transaction_id' => null,
                    'amount'                 => self::absolute($metalDelta),
                    'type'                   => $metalType,
                    'balance_before'         => $metalBefore,
                    'balance_after'          => $metalAfter,
                    'description'            => sprintf('ثبت فلز سفارش نقد حاضر #%s', $order->getKey()),
                ]);

                $fiatTransaction = WalletTransaction::create([
                    'metal_trader_wallet_id' => $cashWallet->getKey(),
                    'metal_order_id'         => $order->getKey(),
                    'related_transaction_id' => $metalTransaction->getKey(),
                    'amount'                 => self::absolute($fiatDelta),
                    'type'                   => $fiatType,
                    'balance_before'         => $cashBefore,
                    'balance_after'          => $cashAfter,
                    'description'            => sprintf(
                        '%s تومان سفارش نقد حاضر #%s',
                        $isBuy ? 'کسر' : 'واریز',
                        $order->getKey()
                    ),
                ]);
            } else {
                $fiatBefore = self::decimal($wallet->fiat_balance ?? '0', self::FIAT_SCALE, 'fiat_balance');
                $fiatAfter  = bcadd($fiatBefore, $fiatDelta, self::FIAT_SCALE);

                $wallet->forceFill([
                    'available_balance' => $metalAfter,
                    'fiat_balance'      => $fiatAfter,
                    'avg_buy_price'     => $avgAfter,
                ])->save();

                $metalTransaction = WalletTransaction::create([
                    'metal_trader_wallet_id' => $wallet->getKey(),
                    'metal_order_id'         => $order->getKey(),
                    'related_transaction_id' => null,
                    'amount'                 => self::absolute($metalDelta),
                    'type'                   => $metalType,
                    'balance_before'         => $metalBefore,
                    'balance_after'          => $metalAfter,
                    'description'            => sprintf('ثبت تعهد فلزی سفارش #%s', $order->getKey()),
                ]);

                $fiatTransaction = WalletTransaction::create([
                    'metal_trader_wallet_id' => $wallet->getKey(),
                    'metal_order_id'         => $order->getKey(),
                    'related_transaction_id' => $metalTransaction->getKey(),
                    'amount'                 => self::absolute($fiatDelta),
                    'type'                   => $fiatType,
                    'balance_before'         => $fiatBefore,
                    'balance_after'          => $fiatAfter,
                    'description'            => sprintf('ثبت تعهد تومانی سفارش #%s', $order->getKey()),
                ]);
            }

            $metalTransaction->forceFill([
                'related_transaction_id' => $fiatTransaction->getKey(),
            ])->save();
        }, 3);
    }

    private static function getOrCreateCashWallet(int $traderId): MetalTraderWallet
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
                'metal_trader_id'   => $traderId,
                'metal_item_id'     => null,
                'available_balance' => '0',
                'blocked_balance'   => '0',
                'fiat_balance'      => '0',
                'avg_buy_price'     => '0',
            ]);
        } catch (\Illuminate\Database\QueryException) {
            return MetalTraderWallet::query()
                ->where('metal_trader_id', $traderId)
                ->whereNull('metal_item_id')
                ->firstOrFail();
        }
    }

    /**
     * مدیریت تغییرات میانگین قیمت برای معاملات Long و Short
     */
    private static function calculateNewAverage(
        string $metalBefore,
        string $avgBefore,
        string $metalAfter,
        string $quantity,
        string $fiatAmount,
        string $orderUnitPrice,
        bool $isBuy
    ): string {
        // ۱. اگر بعد از معامله پوزیشن کلاً صفر شد، میانگین ریست می‌شود
        if (bccomp($metalAfter, '0', self::METAL_SCALE) === 0) {
            return '0';
        }

        // ۲. معامله خرید (Buy)
        if ($isBuy) {
            // الف) کاربر قبلاً مثبت بوده یا صفر بوده و طلا خریده (افزایش پوزیشن Long)
            if (bccomp($metalBefore, '0', self::METAL_SCALE) >= 0) {
                $costBefore = bcmul($metalBefore, $avgBefore, self::METAL_SCALE);
                $newTotalCost = bcadd($costBefore, $fiatAmount, self::METAL_SCALE);
                return self::bcdivRound($newTotalCost, $metalAfter, self::PRICE_SCALE);
            }

            // ب) کاربر قبلاً منفی بوده (Short) و خرید کرده تا تعهدش را ببندد (Cover)
            // اگر بعد از خرید همچنان منفی باشد، میانگین فروش‌های قبلی دست‌نخورده باقی می‌ماند
            if (bccomp($metalAfter, '0', self::METAL_SCALE) < 0) {
                return $avgBefore;
            }

            // ج) کاربر قبلاً منفی بوده و آن‌قدر خریده که مثبت شده (Flip از Short به Long)
            // میانگین طلاهای باقی‌مانده برابر با نرخ همین خرید جدید می‌شود
            return $orderUnitPrice;
        }

        // ۳. معامله فروش (Sell)
        // الف) کاربر قبلاً منفی بوده یا صفر بوده و دوباره فروخته (افزایش پوزیشن بدهی Short)
        if (bccomp($metalBefore, '0', self::METAL_SCALE) <= 0) {
            $absBefore = self::absolute($metalBefore);
            $absAfter  = self::absolute($metalAfter);

            $costBefore = bcmul($absBefore, $avgBefore, self::METAL_SCALE);
            $newTotalCost = bcadd($costBefore, $fiatAmount, self::METAL_SCALE);
            return self::bcdivRound($newTotalCost, $absAfter, self::PRICE_SCALE);
        }

        // ب) کاربر قبلاً طلا داشته و مقداری از آن را فروخته و همچنان مثبت است
        // میانگین خرید دارایی باقیمانده دست‌نخورده باقی می‌ماند
        if (bccomp($metalAfter, '0', self::METAL_SCALE) > 0) {
            return $avgBefore;
        }

        // ج) کاربر قبلاً مثبت بوده و آن‌قدر فروخته که منفی شده (Flip از Long به Short)
        // میانگین تعهد بدهی طلا برابر با نرخ همین فروش جدید می‌شود
        return $orderUnitPrice;
    }

    private static function decimal(mixed $value, int $scale, string $field): string
    {
        if (! is_numeric($value)) {
            throw new LogicException(sprintf('مقدار %s عددی معتبر نیست.', $field));
        }

        return bcadd((string) $value, '0', $scale);
    }

    private static function absolute(string $value): string
    {
        return str_starts_with($value, '-') ? substr($value, 1) : $value;
    }

    private static function roundHalfUp(string $value, int $scale = 0): string
    {
        $adjust = '0.' . str_repeat('0', max($scale - 1, 0)) . '5';

        if (str_starts_with($value, '-')) {
            return bcsub($value, $adjust, $scale);
        }

        return bcadd($value, $adjust, $scale);
    }

    private static function bcdivRound(string $num, string $den, int $scale): string
    {
        if (bccomp($den, '0', self::METAL_SCALE) === 0) {
            return '0';
        }

        $raw = bcdiv($num, $den, $scale + 4);
        $rounded = self::roundHalfUp($raw, $scale);

        return bcadd($rounded, '0', $scale);
    }
}
