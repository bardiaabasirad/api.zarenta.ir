<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Models\MetalTraderWallet;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function index()
    {
        $metalTrader = Auth::guard('metal-trader-api')->user();

        // ۱. تنظیم بازه زمانی (از ابتدای روز شروع تا انتهای روز پایان)
        $startDate = Carbon::parse(request()->input('start', now()->subMonth()))->startOfDay();
        $endDate   = Carbon::parse(request()->input('end', now()))->endOfDay();

        // ۲. کست کردن pageSize به عدد صحیح با مقدار پیش‌فرض
        $pageSize  = (int) request()->input('pageSize', 100000);

        // ۳. استخراج آی‌دی کیف‌پول‌ها
        $walletIds = MetalTraderWallet::where('metal_trader_id', $metalTrader->id)->pluck('id');

        // ۴. اجرای کوئری با اعمال فیلتر تاریخ و limit
        $transactions = WalletTransaction::query()
            ->leftJoin('metal_trader_wallets', 'metal_trader_wallets.id', '=', 'wallet_transactions.metal_trader_wallet_id')
            ->leftJoin('metal_items', 'metal_items.id', '=', 'metal_trader_wallets.metal_item_id')
            ->whereIn('wallet_transactions.metal_trader_wallet_id', $walletIds)
            ->whereBetween('wallet_transactions.created_at', [$startDate, $endDate])
            ->select([
                'wallet_transactions.*',
                'metal_items.title as metal_item_title',
                'metal_items.unit as metal_item_unit',
            ])
            ->orderBy('wallet_transactions.created_at', 'desc')
            ->limit($pageSize)
            ->get();

        return response()->json($transactions);
    }
}
