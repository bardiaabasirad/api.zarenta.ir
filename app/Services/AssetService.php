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
    // این مقادیر را با precision واقعی ستون‌های دیتابیس هماهنگ کن.
    private const METAL_SCALE = 4;
    private const FIAT_SCALE = 0; // اگر مبلغ اعشاری است، مطابق schema تغییر بده.

    public static function updateAsset(MetalOrder $metalOrder): void
    {
        if ($metalOrder->created_type !== 'metal_trader') {
            return;
        }

        DB::transaction(function () use ($metalOrder): void {
            // از دوبار اعمال شدن هم‌زمان یک سفارش جلوگیری می‌کند.
            $order = MetalOrder::query()
                ->whereKey($metalOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->created_type !== 'metal_trader') {
                return;
            }

            // null در طراحی شما کیف‌پول تومانی است، نه کیف‌پول فلز.
            if ($order->metal_item_id === null) {
                throw new LogicException(
                    'برای ثبت معاملهٔ فلزی، metal_item_id نباید null باشد.'
                );
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

            /*
             * Idempotency: اگر این سفارش قبلاً با هر دو ردیف ثبت شده،
             * اجرای مجدد نباید موجودی را دوباره تغییر دهد.
             */
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
                throw new RuntimeException(
                    'برای این سفارش فقط بخشی از تراکنش‌های دفتر کل وجود دارد.'
                );
            }

            $quantity = self::decimal(
                data_get($order->product, 'quantity'),
                self::METAL_SCALE,
                'quantity'
            );

            $fiatAmount = self::decimal(
                data_get($order->product, 'amount'),
                self::FIAT_SCALE,
                'amount'
            );

            if (bccomp($quantity, '0', self::METAL_SCALE) <= 0) {
                throw new LogicException('مقدار فلز باید بزرگ‌تر از صفر باشد.');
            }

            if (bccomp($fiatAmount, '0', self::FIAT_SCALE) <= 0) {
                throw new LogicException('مبلغ معامله باید بزرگ‌تر از صفر باشد.');
            }

            // مقدارهای علامت‌دار برای محاسبهٔ مانده‌ها
            $metalDelta = $isBuy
                ? $quantity
                : bcsub('0', $quantity, self::METAL_SCALE);

            // خرید: بدهی بیشتر؛ فروش: طلب/اعتبار بیشتر
            $fiatDelta = $isBuy
                ? bcsub('0', $fiatAmount, self::FIAT_SCALE)
                : $fiatAmount;

            /*
             * بهتر است این کیف‌پول‌ها از قبل ساخته شوند.
             * firstOrCreate هم به unique مناسب روی جدول نیاز دارد.
             */
            $wallet = MetalTraderWallet::query()->firstOrCreate(
                [
                    'metal_trader_id' => $order->created_id,
                    'metal_item_id'   => $order->metal_item_id,
                ],
                [
                    'available_balance' => '0',
                    'blocked_balance'   => '0',
                    'fiat_balance'      => '0',
                ]
            );

            // قفل کیف‌پول: سفارش‌های هم‌زمان ماندهٔ قدیمی یکسان نمی‌خوانند.
            $wallet = MetalTraderWallet::query()
                ->whereKey($wallet->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $metalBefore = self::decimal(
                $wallet->available_balance ?? '0',
                self::METAL_SCALE,
                'available_balance'
            );

            $fiatBefore = self::decimal(
                $wallet->fiat_balance ?? '0',
                self::FIAT_SCALE,
                'fiat_balance'
            );

            $metalAfter = bcadd(
                $metalBefore,
                $metalDelta,
                self::METAL_SCALE
            );

            $fiatAfter = bcadd(
                $fiatBefore,
                $fiatDelta,
                self::FIAT_SCALE
            );

            // ثبت ماندهٔ جدید
            $wallet->forceFill([
                'available_balance' => $metalAfter,
                'fiat_balance'      => $fiatAfter,
            ])->save();

            // ردیف دفتر کل فلز
            $metalTransaction = WalletTransaction::create([
                'metal_trader_wallet_id' => $wallet->getKey(),
                'metal_order_id'         => $order->getKey(),
                'related_transaction_id' => null,
                'amount'                 => self::absolute($metalDelta),
                'type'                   => $metalType,
                'balance_before'         => $metalBefore,
                'balance_after'          => $metalAfter,
                'description'            => sprintf(
                    'ثبت تعهد فلزی سفارش #%s',
                    $order->getKey()
                ),
            ]);

            // ردیف دفتر کل فیاتِ تعهدی
            $fiatTransaction = WalletTransaction::create([
                'metal_trader_wallet_id' => $wallet->getKey(),
                'metal_order_id'         => $order->getKey(),
                'related_transaction_id' => $metalTransaction->getKey(),
                'amount'                 => self::absolute($fiatDelta),
                'type'                   => $fiatType,
                'balance_before'         => $fiatBefore,
                'balance_after'          => $fiatAfter,
                'description'            => sprintf(
                    'ثبت تعهد ریالی سفارش #%s',
                    $order->getKey()
                ),
            ]);

            // اتصال دو ردیف به یکدیگر
            $metalTransaction->forceFill([
                'related_transaction_id' => $fiatTransaction->getKey(),
            ])->save();
        }, 3);
    }

    private static function decimal(
        mixed $value,
        int $scale,
        string $field
    ): string {
        if (! is_numeric($value)) {
            throw new LogicException(
                sprintf('مقدار %s عددی معتبر نیست.', $field)
            );
        }

        return bcadd((string) $value, '0', $scale);
    }

    private static function absolute(string $value): string
    {
        return str_starts_with($value, '-')
            ? substr($value, 1)
            : $value;
    }
}
