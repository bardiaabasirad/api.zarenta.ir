<?php

namespace App\Services;

use App\Models\MetalItem;
use App\Models\MetalOrder;
use App\Models\MetalTraderWallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Morilog\Jalali\Jalalian;

class SettlementService
{
    const METAL_SCALE = 3;
    const FIAT_SCALE  = 0;

    // مقیاس‌های محاسباتی با دقت بالا
    private const CALC_SCALE  = 8;
    private const PRICE_SCALE = 0;

    /**
     * پیاده‌سازی قدرمطلق با BCMath بدون وابستگی به توابع ناموجود
     */
    private static function bcabs(string $number, int $scale = 4): string
    {
        return (bccomp($number, '0', $scale) < 0)
            ? bcmul($number, '-1', $scale)
            : bcadd($number, '0', $scale);
    }

    /**
     * تقسیم مثبت همراه با گردکردن Half-Up (ریاضی)
     * جهت محاسبه دقیق بهای تمام‌شده و میانگین موزون
     */
    private static function bcdivRoundPositive(string $numerator, string $denominator, int $scale): string
    {
        if (bccomp($denominator, '0', self::CALC_SCALE) === 0) {
            throw new \DivisionByZeroError('Division by zero in bcdivRoundPositive');
        }

        // یک رقم اعشار بیشتر برای بررسی قاعده گرد کردن
        $raw = bcdiv($numerator, $denominator, $scale + 1);

        // برش تا scale مورد نظر
        $truncated = bcadd($raw, '0', $scale);

        $parts = explode('.', $raw, 2);
        $fraction = $parts[1] ?? '';
        $roundDigit = isset($fraction[$scale]) ? (int) $fraction[$scale] : 0;

        if ($roundDigit >= 5) {
            $inc = ($scale === 0)
                ? '1'
                : '0.' . str_repeat('0', $scale - 1) . '1';

            $truncated = bcadd($truncated, $inc, $scale);
        }

        return $truncated;
    }

    /**
     * اعمال تغییرات پوزیشن (Delta) روی کیف‌پول و به‌روزرسانی میانگین موزون (MWAC)
     *
     * قواعد:
     * ۱. اگر delta هم‌جهت با پوزیشن قبلی باشد => میانگین موزون (MWAC) جدید محاسبه می‌شود.
     * ۲. اگر delta خلاف جهت باشد:
     *    - پوزیشن صفر شود => میانگین ریست (۰) می‌شود.
     *    - پوزیشن کاهش یابد اما تغییر جهت ندهد => میانگین بدون تغییر باقی می‌ماند.
     *    - پوزیشن Flip شود (معکوس شود) => میانگین برابر با نرخ انتقال/معامله جدید می‌شود.
     *
     * @return array{0: string, 1: string} [balanceAfter, avgAfter]
     */
    private static function applyPositionDelta(
        string $balanceBefore,
        string $avgBefore,
        string $delta,
        string $transactionPrice
    ): array {
        $balanceBefore = bcadd($balanceBefore, '0', self::METAL_SCALE);
        $avgBefore     = bcadd($avgBefore ?? '0', '0', self::PRICE_SCALE);
        $delta         = bcadd($delta, '0', self::METAL_SCALE);

        $balanceAfter = bcadd($balanceBefore, $delta, self::METAL_SCALE);

        if (bccomp($delta, '0', self::METAL_SCALE) === 0) {
            return [$balanceAfter, $avgBefore];
        }

        // اگر پوزیشن نهایی صفر شد، بهای تمام‌شده ریست می‌شود
        if (bccomp($balanceAfter, '0', self::METAL_SCALE) === 0) {
            return [$balanceAfter, '0'];
        }

        $beforeDir = bccomp($balanceBefore, '0', self::METAL_SCALE); // -1, 0, 1
        $deltaDir  = bccomp($delta, '0', self::METAL_SCALE);         // -1, 0, 1

        // اگر کیف‌پول قبلاً خالی بوده، با نرخ انتقال مقداردهی می‌شود
        if ($beforeDir === 0) {
            return [$balanceAfter, bcadd($transactionPrice, '0', self::PRICE_SCALE)];
        }

        // افزایش حجم در همان جهت => میانگین موزون جدید
        if ($beforeDir === $deltaDir) {
            $oldQty = self::bcabs($balanceBefore, self::METAL_SCALE);
            $addQty = self::bcabs($delta, self::METAL_SCALE);
            $newQty = self::bcabs($balanceAfter, self::METAL_SCALE);

            $oldCost = bcmul($oldQty, $avgBefore, self::CALC_SCALE);
            $addCost = bcmul($addQty, bcadd($transactionPrice, '0', self::PRICE_SCALE), self::CALC_SCALE);
            $total   = bcadd($oldCost, $addCost, self::CALC_SCALE);

            $newAvg = self::bcdivRoundPositive($total, $newQty, self::PRICE_SCALE);

            return [$balanceAfter, $newAvg];
        }

        // اگر خلاف جهت بود، وضعیت پوزیشن نهایی را بررسی می‌کنیم
        $afterDir = bccomp($balanceAfter, '0', self::METAL_SCALE);

        // پوزیشن فقط کاهش یافته و Flip نشده => میانگین دست‌نخورده می‌ماند
        if ($afterDir === $beforeDir) {
            return [$balanceAfter, $avgBefore];
        }

        // اگر پوزیشن معکوس (Flip) شده باشد => میانگین برابر با بهای انتقال جدید می‌شود
        return [$balanceAfter, bcadd($transactionPrice, '0', self::PRICE_SCALE)];
    }

    public static function settleOrdersForTrader(int $traderId, int $commitmentMetalItemId, string $settlementDate): void
    {
        $jalaliDate = Jalalian::fromCarbon(\Carbon\Carbon::parse($settlementDate))->format('Y/m/d');

        DB::transaction(function () use ($jalaliDate, $traderId, $commitmentMetalItemId, $settlementDate) {

            // ۱. دریافت سفارش‌های معلق تریدر تا سررسید مد نظر
            $orders = MetalOrder::query()
                ->where('created_type', 'metal_trader')
                ->where('created_id', $traderId)
                ->where('metal_item_id', $commitmentMetalItemId)
                ->whereDate('settlement_date', '<=', $settlementDate)
                ->where('settlement_status', 'pending')
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                Log::warning("[SETTLEMENT] No pending orders found for trader #{$traderId} and item #{$commitmentMetalItemId}.");
                return;
            }

            // ۲. پیدا کردن آیتم حاضر مقصد (Spot Item)
            $commitmentItem = MetalItem::findOrFail($commitmentMetalItemId);
            $spotItemId = (int) $commitmentItem->settlement_metal_item_id;

            if (! $spotItemId) {
                throw new \RuntimeException("settlement_metal_item_id برای آیتم تعهدی #{$commitmentMetalItemId} تنظیم نشده است.");
            }

            // ۳. محاسبه برآیند خالص فلز و تومان
            $netMetal = '0';
            $netFiat  = '0';

            foreach ($orders as $order) {
                $qty    = (string) data_get($order->product, 'quantity', '0');
                $amount = (string) data_get($order->product, 'amount', '0');

                if ($order->order_type === 'buy') {
                    $netMetal = bcadd($netMetal, $qty, self::METAL_SCALE);
                    $netFiat  = bcsub($netFiat, $amount, self::FIAT_SCALE);
                } elseif ($order->order_type === 'sell') {
                    $netMetal = bcsub($netMetal, $qty, self::METAL_SCALE);
                    $netFiat  = bcadd($netFiat, $amount, self::FIAT_SCALE);
                }
            }

            // ۴. تسویه طلا (Metal Settlement) و انتقال بهای تمام‌شده به کیف‌پول حاضر
            if (bccomp($netMetal, '0', self::METAL_SCALE) !== 0) {

                // الف) کیف‌پول تعهدی (مبدأ)
                $commitmentMetalWallet = MetalTraderWallet::query()
                    ->where('metal_trader_id', $traderId)
                    ->where('metal_item_id', $commitmentMetalItemId)
                    ->lockForUpdate()
                    ->first();

                if (! $commitmentMetalWallet) {
                    throw new \RuntimeException("کیف‌پول تعهدی برای تریدر #{$traderId} و آیتم #{$commitmentMetalItemId} یافت نشد.");
                }

                // ب) کیف‌پول حاضر (مقصد)
                $spotMetalWallet = self::getOrCreateWallet($traderId, $spotItemId);

                // قفل کردن ردیف مقصد با lockForUpdate
                $spotMetalWallet = MetalTraderWallet::query()
                    ->whereKey($spotMetalWallet->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldCommitmentBalance = bcadd((string) $commitmentMetalWallet->available_balance, '0', self::METAL_SCALE);
                $oldSpotBalance       = bcadd((string) $spotMetalWallet->available_balance, '0', self::METAL_SCALE);

                $commitmentAvg = bcadd((string) ($commitmentMetalWallet->avg_buy_price ?? '0'), '0', self::PRICE_SCALE);
                $spotAvg       = bcadd((string) ($spotMetalWallet->avg_buy_price ?? '0'), '0', self::PRICE_SCALE);

                $absMetal   = self::bcabs($netMetal, self::METAL_SCALE);
                $isNetBuyer = bccomp($netMetal, '0', self::METAL_SCALE) > 0;

                // نگاشت تایپ‌های لجر بر اساس جهت ورود و خروج
                $commitmentType = $isNetBuyer ? 'settlement_metal_out' : 'settlement_metal_in';
                $spotType       = $isNetBuyer ? 'settlement_metal_in'  : 'settlement_metal_out';

                // ج) به‌روزرسانی موجودی و میانگین مبدأ: commitment -= netMetal
                $newCommitmentBalance = bcsub($oldCommitmentBalance, $netMetal, self::METAL_SCALE);
                $newCommitmentAvg     = (bccomp($newCommitmentBalance, '0', self::METAL_SCALE) === 0)
                    ? '0'
                    : $commitmentAvg;

                $commitmentMetalWallet->forceFill([
                    'available_balance' => $newCommitmentBalance,
                    'avg_buy_price'     => $newCommitmentAvg,
                ])->save();

                // د) به‌روزرسانی موجودی و میانگین مقصد: spot += netMetal
                [$newSpotBalance, $newSpotAvg] = self::applyPositionDelta(
                    balanceBefore: $oldSpotBalance,
                    avgBefore: $spotAvg,
                    delta: $netMetal,
                    transactionPrice: $commitmentAvg
                );

                $spotMetalWallet->forceFill([
                    'available_balance' => $newSpotBalance,
                    'avg_buy_price'     => $newSpotAvg,
                ])->save();

                // هـ) تراکنش لجر تعهدی
                $txCommitment = WalletTransaction::create([
                    'metal_trader_wallet_id' => $commitmentMetalWallet->id,
                    'created_type'           => 'metal_trader',
                    'created_id'             => $traderId,
                    'type'                   => $commitmentType,
                    'amount'                 => $absMetal,
                    'balance_before'         => $oldCommitmentBalance,
                    'balance_after'          => $newCommitmentBalance,
                    'related_transaction_id' => null,
                    'description'            => "تسویه نماد تعهدی به حاضر - سررسید {$jalaliDate}",
                ]);

                // و) تراکنش لجر حاضر
                $txSpot = WalletTransaction::create([
                    'metal_trader_wallet_id' => $spotMetalWallet->id,
                    'created_type'           => 'metal_trader',
                    'created_id'             => $traderId,
                    'type'                   => $spotType,
                    'amount'                 => $absMetal,
                    'balance_before'         => $oldSpotBalance,
                    'balance_after'          => $newSpotBalance,
                    'related_transaction_id' => $txCommitment->id,
                    'description'            => "دریافت/تحویل طلای تسویه شده سررسید {$jalaliDate}",
                ]);

                // زنجیره پیوند لجر
                $txCommitment->update(['related_transaction_id' => $txSpot->id]);
            }

            // ۵. تسویه مبلغ تومانی (Fiat Settlement)
            if (bccomp($netFiat, '0', self::FIAT_SCALE) !== 0) {

                // الف) کیف‌پول تومانی تعهدی (مبدأ)
                $commitmentFiatWallet = MetalTraderWallet::query()
                    ->where('metal_trader_id', $traderId)
                    ->where('metal_item_id', $commitmentMetalItemId)
                    ->lockForUpdate()
                    ->first();

                if (! $commitmentFiatWallet) {
                    throw new \RuntimeException("کیف‌پول تومانی تعهدی برای تریدر #{$traderId} یافت نشد.");
                }

                // ب) کیف‌پول تومانی حاضر (مقصد)
                $spotFiatWallet = self::getOrCreateFiatWallet($traderId);

                $spotFiatWallet = MetalTraderWallet::query()
                    ->whereKey($spotFiatWallet->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $absFiat = self::bcabs($netFiat, self::FIAT_SCALE);
                $isBuyer = bccomp($netFiat, '0', self::FIAT_SCALE) < 0;

                // ۱) آپدیت تراز کیف تعهدی: commitment -= netFiat (خنثی‌سازی تعهد ریالی)
                $oldCommitmentFiat = bcadd((string) ($commitmentFiatWallet->fiat_balance ?? '0'), '0', self::FIAT_SCALE);
                $newCommitmentFiat = bcsub($oldCommitmentFiat, $netFiat, self::FIAT_SCALE);

                $commitmentFiatWallet->fiat_balance = $newCommitmentFiat;
                $commitmentFiatWallet->save();

                $txCommitmentFiat = WalletTransaction::create([
                    'metal_trader_wallet_id' => $commitmentFiatWallet->id,
                    'created_type'           => 'metal_trader',
                    'created_id'             => $traderId,
                    'type'                   => 'settlement_fiat_transfer',
                    'amount'                 => $absFiat,
                    'balance_before'         => $oldCommitmentFiat,
                    'balance_after'          => $newCommitmentFiat,
                    'related_transaction_id' => null,
                    'description'            => $isBuyer
                        ? "تسویه بدهی تومانی تعهدی - سررسید {$jalaliDate}"
                        : "تسویه بستانکاری تومانی تعهدی - سررسید {$jalaliDate}",
                ]);

                // ۲) آپدیت تراز کیف حاضر: spot += netFiat
                $oldSpotFiat = bcadd((string) ($spotFiatWallet->available_balance ?? '0'), '0', self::FIAT_SCALE);
                $newSpotFiat = bcadd($oldSpotFiat, $netFiat, self::FIAT_SCALE);

                $spotFiatWallet->available_balance = $newSpotFiat;
                $spotFiatWallet->save();

                $txSpotFiat = WalletTransaction::create([
                    'metal_trader_wallet_id' => $spotFiatWallet->id,
                    'created_type'           => 'metal_trader',
                    'created_id'             => $traderId,
                    'type'                   => 'settlement_fiat_transfer',
                    'amount'                 => $absFiat,
                    'balance_before'         => $oldSpotFiat,
                    'balance_after'          => $newSpotFiat,
                    'related_transaction_id' => $txCommitmentFiat->id,
                    'description'            => $isBuyer
                        ? "انتقال بدهی تومانی به کیف‌پول حاضر - سررسید {$jalaliDate}"
                        : "واریز وجه حاصل از تسویه فروش به کیف‌پول حاضر - سررسید {$jalaliDate}",
                ]);

                // اتصال زنجیره لجر دوطرفه
                $txCommitmentFiat->update([
                    'related_transaction_id' => $txSpotFiat->id,
                ]);
            }

            // ۶. آپدیت وضعیت سفارش‌های سررسیدشده
            MetalOrder::whereIn('id', $orders->pluck('id'))
                ->update(['settlement_status' => 'settled']);
        });
    }

    private static function getOrCreateWallet(int $traderId, int $metalItemId): MetalTraderWallet
    {
        $wallet = MetalTraderWallet::query()
            ->where('metal_trader_id', $traderId)
            ->where('metal_item_id', $metalItemId)
            ->lockForUpdate()
            ->first();

        if ($wallet) {
            return $wallet;
        }

        try {
            return MetalTraderWallet::query()->create([
                'metal_trader_id'   => $traderId,
                'metal_item_id'     => $metalItemId,
                'available_balance' => '0',
                'locked_balance'    => '0',
                'avg_buy_price'     => '0',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return MetalTraderWallet::query()
                ->where('metal_trader_id', $traderId)
                ->where('metal_item_id', $metalItemId)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }

    private static function getOrCreateFiatWallet(int $traderId): MetalTraderWallet
    {
        $wallet = MetalTraderWallet::query()
            ->where('metal_trader_id', $traderId)
            ->whereNull('metal_item_id')
            ->lockForUpdate()
            ->first();

        if ($wallet) {
            return $wallet;
        }

        try {
            return MetalTraderWallet::query()->create([
                'metal_trader_id'   => $traderId,
                'metal_item_id'     => null,
                'available_balance' => '0',
                'locked_balance'    => '0',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return MetalTraderWallet::query()
                ->where('metal_trader_id', $traderId)
                ->whereNull('metal_item_id')
                ->lockForUpdate()
                ->firstOrFail();
        }
    }
}
