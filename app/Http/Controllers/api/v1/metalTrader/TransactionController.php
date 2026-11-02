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

        $startDate = Carbon::parse(request()->input('start', now()->subMonth()))->startOfDay();
        $endDate   = Carbon::parse(request()->input('end', now()))->endOfDay();

        $perPage = (int) request()->input('count', request()->input('pageSize', config('app.per_page', 50)));
        $perPage = max(1, min($perPage, 100));

        $walletIds = MetalTraderWallet::where('metal_trader_id', $metalTrader->id)->pluck('id');

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
            ->paginate($perPage);

        return response()->json(['data' => $transactions]);
    }
}
