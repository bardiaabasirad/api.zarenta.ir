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
    const METAL_SCALE = 4;
    const FIAT_SCALE  = 2;

    public static function settleOrdersForTrader(int $traderId, int $commitmentMetalItemId, string $settlementDate): void
    {
        Log::info("--- [SETTLEMENT START] ---", [
            'trader_id' => $traderId,
            'commitment_metal_item_id' => $commitmentMetalItemId,
            'settlement_date' => $settlementDate,
        ]);

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

            Log::info("[SETTLEMENT] Orders Count fetched", [
                'count' => $orders->count(),
                'order_ids' => $orders->pluck('id')->toArray()
            ]);

            if ($orders->isEmpty()) {
                Log::warning("[SETTLEMENT] No pending orders found. Exiting.");
                return;
            }

            // ۲. پیدا کردن آیتم مقصد
            $commitmentItem = MetalItem::find($commitmentMetalItemId);
            $spotItemId = $commitmentItem?->settlement_metal_item_id;

            Log::info("[SETTLEMENT] Metal Items Config", [
                'commitment_item_id' => $commitmentItem?->id,
                'spot_item_id' => $spotItemId
            ]);

            // ۳. محاسبه برآیندها
            $netMetal = '0';
            $netFiat  = '0';

            foreach ($orders as $order) {
                $qty = (string) data_get($order->product, 'quantity', '0');
                $amount = (string) data_get($order->product, 'amount', '0');

                Log::info("[SETTLEMENT] Parsing Order #{$order->id}", [
                    'side' => $order->side,
                    'raw_product' => $order->product,
                    'extracted_qty' => $qty,
                    'extracted_amount' => $amount
                ]);

                if ($order->side === 'buy') {
                    $netMetal = bcadd($netMetal, $qty, self::METAL_SCALE);
                    $netFiat  = bcsub($netFiat, $amount, self::FIAT_SCALE);
                } elseif ($order->side === 'sell') {
                    $netMetal = bcsub($netMetal, $qty, self::METAL_SCALE);
                    $netFiat  = bcadd($netFiat, $amount, self::FIAT_SCALE);
                }
            }

            Log::info("[SETTLEMENT] Net Calculations Result", [
                'netMetal' => $netMetal,
                'netFiat'  => $netFiat,
                'netMetal_is_zero' => bccomp($netMetal, '0', self::METAL_SCALE) === 0,
                'netFiat_is_zero'  => bccomp($netFiat, '0', self::FIAT_SCALE) === 0,
            ]);

            // ۴. تسویه طلا
            if (bccomp($netMetal, '0', self::METAL_SCALE) !== 0) {
                Log::info("[SETTLEMENT] Processing Metal Settlement...");

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

                Log::info("[SETTLEMENT] Metal Wallets Fetched", [
                    'commitment_wallet_id' => $commitmentMetalWallet?->id,
                    'spot_wallet_id' => $spotMetalWallet?->id,
                ]);

                if ($commitmentMetalWallet && $spotMetalWallet) {
                    $absMetal = bcabs($netMetal, self::METAL_SCALE);

                    // کاهش/افزایش تعهدی
                    $oldCommitmentBalance = $commitmentMetalWallet->available_balance;
                    $commitmentMetalWallet->available_balance = bcsub($commitmentMetalWallet->available_balance, $netMetal, self::METAL_SCALE);
                    $commitmentMetalWallet->save();

                    Log::info("[SETTLEMENT] Attempting to create Commitment Metal Transaction");

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

                    Log::info("[SETTLEMENT] Commitment Metal Tx Created", ['tx_id' => $txCommitment->id]);

                    // تغییر کیف‌پول حاضر
                    $oldSpotBalance = $spotMetalWallet->available_balance;
                    $spotMetalWallet->available_balance = bcadd($spotMetalWallet->available_balance, $netMetal, self::METAL_SCALE);
                    $spotMetalWallet->save();

                    Log::info("[SETTLEMENT] Attempting to create Spot Metal Transaction");

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

                    Log::info("[SETTLEMENT] Spot Metal Tx Created", ['tx_id' => $txSpot->id]);

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

            Log::info("[SETTLEMENT] Orders Updated to settled", ['count' => $updatedOrders]);
            Log::info("--- [SETTLEMENT END] ---");
        });
    }
}
