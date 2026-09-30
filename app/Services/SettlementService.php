<?php

namespace App\Services;

use App\Models\MetalItem;
use App\Models\MetalOrder;
use App\Models\MetalTraderWallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        DB::transaction(function () use ($traderId, $commitmentMetalItemId, $settlementDate) {

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
                Log::warning("[SETTLEMENT] No pending orders found. Exiting.");
                return;
            }

            // ۲. پیدا کردن آیتم مقصد
            $spotItemId = MetalItem::find($commitmentMetalItemId)->pluck('settlement_metal_item_id')->first();

            // ۳. محاسبه برآیندها
            $netMetal = '0';
            $netFiat  = '0';

            foreach ($orders as $order) {
                $qty = (string) data_get($order->product, 'quantity', '0');
                $amount = (string) data_get($order->product, 'amount', '0');

                if ($order->order_type === 'buy') {
                    $netMetal = bcadd($netMetal, $qty, self::METAL_SCALE);
                    $netFiat  = bcsub($netFiat, $amount, self::FIAT_SCALE);
                } elseif ($order->order_type === 'sell') {
                    $netMetal = bcsub($netMetal, $qty, self::METAL_SCALE);
                    $netFiat  = bcadd($netFiat, $amount, self::FIAT_SCALE);
                }
            }

            Log::info($netMetal);
            Log::info(bccomp($netMetal, '0', self::METAL_SCALE));
            return;

            // ۴. تسویه طلا
            if (bccomp($netMetal, '0', self::METAL_SCALE) !== 0) {
                $commitmentMetalWallet = MetalTraderWallet::query()
                    ->where('metal_trader_id', $traderId)
                    ->where('metal_item_id', $commitmentMetalItemId)
                    ->lockForUpdate()
                    ->first();

                $spotMetalWallet = MetalTraderWallet::query()
                    ->where('metal_trader_id', $traderId)
                    ->where('metal_item_id', $spotItemId)
                    ->lockForUpdate()
                    ->first();

                if ($commitmentMetalWallet && $spotMetalWallet) {
                    $absMetal = self::bcabs($netMetal, self::METAL_SCALE);

                    // کاهش/افزایش تعهدی
                    $oldCommitmentBalance = $commitmentMetalWallet->available_balance;
                    $commitmentMetalWallet->available_balance = bcsub($commitmentMetalWallet->available_balance, $netMetal, self::METAL_SCALE);
                    $commitmentMetalWallet->save();

                    $txCommitment = WalletTransaction::create([
                        'wallet_id'              => $commitmentMetalWallet->id,
                        'created_type'           => 'metal_trader',
                        'created_id'             => $traderId,
                        'type'                   => 'metal_commitment_settle',
                        'amount'                 => $absMetal,
                        'balance_before'         => $oldCommitmentBalance,
                        'balance_after'          => $commitmentMetalWallet->available_balance,
                        'related_transaction_id' => null,
                        'description'            => "تسویه و تبدیل تعهد به حاضر - سررسید {$settlementDate}",
                    ]);

                    // تغییر کیف‌پول حاضر
                    $oldSpotBalance = $spotMetalWallet->available_balance;
                    $spotMetalWallet->available_balance = bcadd($spotMetalWallet->available_balance, $netMetal, self::METAL_SCALE);
                    $spotMetalWallet->save();

                    $txSpot = WalletTransaction::create([
                        'wallet_id'              => $spotMetalWallet->id,
                        'created_type'           => 'metal_trader',
                        'created_id'             => $traderId,
                        'type'                   => 'metal_spot_settle',
                        'amount'                 => $absMetal,
                        'balance_before'         => $oldSpotBalance,
                        'balance_after'          => $spotMetalWallet->available_balance,
                        'related_transaction_id' => $txCommitment->id,
                        'description'            => "دریافت طلای تسویه شده سررسید {$settlementDate}",
                    ]);

                    $txCommitment->update(['related_transaction_id' => $txSpot->id]);
                } else {
                    Log::error("[SETTLEMENT] Metal Wallets Not Found for Settlement!", [
                        'commitment_wallet_found' => (bool)$commitmentMetalWallet,
                        'spot_wallet_found' => (bool)$spotMetalWallet,
                    ]);
                }
            }

            // ۵. آپدیت وضعیت سفارش‌ها
            $updatedOrders = MetalOrder::whereIn('id', $orders->pluck('id'))
                ->update(['settlement_status' => 'settled']);
        });
    }
}
