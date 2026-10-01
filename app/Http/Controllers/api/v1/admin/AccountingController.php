<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManualFiatOperationRequest;
use App\Models\MetalTrader;
use App\Models\MetalTraderWallet;
use App\Models\WalletTransaction;
use App\Services\TraderTransactionPdfService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function transactions()
    {
        $metalTraderId = request('metal_trader_id');

        // ۱. تنظیم بازه زمانی (از ابتدای روز شروع تا انتهای روز پایان)
        $startDate = Carbon::parse(request()->input('start', now()->subMonth()))->startOfDay();
        $endDate   = Carbon::parse(request()->input('end', now()))->endOfDay();

        // ۲. کست کردن pageSize به عدد صحیح با مقدار پیش‌فرض
        $pageSize  = (int) request()->input('pageSize', 100000);

        // ۳. استخراج آی‌دی کیف‌پول‌ها
        $walletIds = MetalTraderWallet::where('metal_trader_id', $metalTraderId)->pluck('id');

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

    public function transactionsToPDF(Request $request, TraderTransactionPdfService $pdfService)
    {
        $metalTrader = MetalTrader::find($request->input('metal_trader_id'));

        return $pdfService->downloadPdf(
            metalTrader: $metalTrader,
            start:       $request->input('start'),
            end:         $request->input('end'),
            pageSize:    (int) $request->input('pageSize', 100)
        );
    }

    public function store(StoreManualFiatOperationRequest $request)
    {
        $data = $request->validated();
        $traderId = (int) $data['metal_trader_id'];
        $type     = $data['type'];
        $amount   = (string) $data['value'];

        $result = DB::transaction(function () use ($traderId, $type, $amount, $data) {
            // ۱. قفل یا ایجاد کیف‌پول تومانی حاضر (metal_item_id = null)
            $wallet = MetalTraderWallet::query()
                ->where('metal_trader_id', $traderId)
                ->whereNull('metal_item_id')
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = MetalTraderWallet::create([
                    'metal_trader_id'   => $traderId,
                    'metal_item_id'     => null,
                    'available_balance' => '0',
                    'locked_balance'    => '0',
                ]);
                // قفل کردن مجدد رکورد ایجاد شده
                $wallet = MetalTraderWallet::where('id', $wallet->id)->lockForUpdate()->first();
            }

            $balanceBefore = (string) $wallet->available_balance;

            // ۲. محاسبه مانده جدید و بررسی اعتبارسنجی برداشت
            if ($type === 'deposit') {
                $balanceAfter = bcadd($balanceBefore, $amount, 0);
            } else { // withdraw
                $balanceAfter = bcsub($balanceBefore, $amount, 0);
            }

            // ۳. به‌روزرسانی موجودی کیف‌پول
            $wallet->available_balance = $balanceAfter;
            $wallet->save();

            // ۴. ثبت سند لجر (WalletTransaction)
            $transaction = WalletTransaction::create([
                'metal_trader_wallet_id' => $wallet->id,
                'metal_order_id'         => null,
                'related_transaction_id' => null,
                'amount'                 => $amount,
                'type'                   => $type,
                'balance_before'         => $balanceBefore,
                'balance_after'          => $balanceAfter,
                'reference_number'       => $data['reference_number'] ?? null,
                'description'            => $data['description'] ?? null,
            ]);

            return [
                'transaction' => $transaction,
                'wallet'      => $wallet,
            ];
        });

        return response()->json([
            'message' => 'تراکنش مالی با موفقیت ثبت و موجودی اعمال شد.',
            'data'    => $result['transaction'],
            'wallet'  => [
                'id'                => $result['wallet']->id,
                'available_balance' => $result['wallet']->available_balance,
            ]
        ], 201);
    }
}
