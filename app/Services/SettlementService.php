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

    /**
     * پیاده‌سازی قدرمطلق با BCMath بدون وابستگی به توابع ناموجود
     */
    private static function bcabs(string $number, int $scale = 4): string
    {
        return (bccomp($number, '0', $scale) < 0)
            ? bcmul($number, '-1', $scale)
            : bcadd($number, '0', $scale);
    }

    public static function settleOrdersForTrader(int $traderId, int $commitmentMetalItemId, string $settlementDate): void
    {
        $jalaliDate = Jalalian::fromCarbon(\Carbon\Carbon::parse($settlementDate))->format('Y/m/d');

        DB::transaction(function () use ($jalaliDate, $traderId, $commitmentMetalItemId, $settlementDate) {

            // ۱. دریافت سفارش‌های معلق
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

            // ۳. محاسبه برآیندها
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

            // ۴. تسویه طلا (Metal Settlement)
            if (bccomp($netMetal, '0', self::METAL_SCALE) !== 0) {

                // ۱. کیف‌پول تعهدی
                $commitmentMetalWallet = MetalTraderWallet::query()
                    ->where('metal_trader_id', $traderId)
                    ->where('metal_item_id', $commitmentMetalItemId)
                    ->lockForUpdate()
                    ->first();

                if (! $commitmentMetalWallet) {
                    throw new \Exception("کیف‌پول تعهدی برای تریدر #{$traderId} و آیتم #{$commitmentMetalItemId} یافت نشد.");
                }

                // ۲. کیف‌پول حاضر (در صورت نبودن ساخته می‌شود)
                $spotMetalWallet = self::getOrCreateWallet($traderId, $spotItemId);

                $absMetal = self::bcabs($netMetal, self::METAL_SCALE);
                $isNetBuyer = bccomp($netMetal, '0', self::METAL_SCALE) > 0;

                // نگاشت تایپ‌های ENUM بر اساس جهت ورود و خروج
                $commitmentType = $isNetBuyer ? 'settlement_metal_out' : 'settlement_metal_in';
                $spotType       = $isNetBuyer ? 'settlement_metal_in'  : 'settlement_metal_out';

                // ۳. آپدیت مانده کیف‌پول تعهدی
                $oldCommitmentBalance = (string) $commitmentMetalWallet->available_balance;
                $newCommitmentBalance = bcsub($oldCommitmentBalance, $netMetal, self::METAL_SCALE);
                $commitmentMetalWallet->available_balance = $newCommitmentBalance;
                $commitmentMetalWallet->save();

                // تراکنش لجر تعهدی
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

                // ۴. آپدیت مانده کیف‌پول حاضر (Spot)
                $oldSpotBalance = (string) $spotMetalWallet->available_balance;
                $newSpotBalance = bcadd($oldSpotBalance, $netMetal, self::METAL_SCALE);
                $spotMetalWallet->available_balance = $newSpotBalance;
                $spotMetalWallet->save();

                // تراکنش لجر حاضر
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

                // الف) کیف‌پول تومانی تعهدی (مبدأ) - رفع باگ متغیر به $commitmentMetalItemId
                $commitmentFiatWallet = MetalTraderWallet::query()
                    ->where('metal_trader_id', $traderId)
                    ->where('metal_item_id', $commitmentMetalItemId)
                    ->lockForUpdate()
                    ->first();

                if (! $commitmentFiatWallet) {
                    throw new \Exception("کیف‌پول تومانی تعهدی برای تریدر #{$traderId} یافت نشد.");
                }

                // ب) کیف‌پول تومانی حاضر (مقصد)
                $spotFiatWallet = self::getOrCreateFiatWallet($traderId);

                $absFiat = self::bcabs($netFiat, self::FIAT_SCALE);
                $isBuyer = bccomp($netFiat, '0', self::FIAT_SCALE) < 0;

                // ۱) آپدیت تراز کیف تعهدی: commitment -= netFiat (خنثی‌سازی و میل به صفر)
                $oldCommitmentFiat = (string) $commitmentFiatWallet->fiat_balance;
                $newCommitmentFiat = bcsub($oldCommitmentFiat, $netFiat, self::FIAT_SCALE);
                $commitmentFiatWallet->fiat_balance = $newCommitmentFiat;
                $commitmentFiatWallet->save();

                // ثبت تراکنش لجر تعهدی با تایپ مجاز settlement_fiat_transfer
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

                // ۲) آپدیت تراز کیف حاضر: spot += netFiat (منفی شدن برای خریدار / مثبت شدن برای فروشنده)
                $oldSpotFiat = (string) $spotFiatWallet->available_balance;
                $newSpotFiat = bcadd($oldSpotFiat, $netFiat, self::FIAT_SCALE);
                $spotFiatWallet->available_balance = $newSpotFiat;
                $spotFiatWallet->save();

                // ثبت تراکنش لجر حاضر با تایپ مجاز و پیوند به تراکنش اول
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

                // اتصال زنجیره حسابداری دوطرفه
                $txCommitmentFiat->update([
                    'related_transaction_id' => $txSpotFiat->id,
                ]);
            }

            // ۶. آپدیت وضعیت سفارش‌ها
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
