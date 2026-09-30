<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\MetalItem;
use App\Models\MetalTraderWallet;
use App\Models\WalletTransaction;
use App\Services\KimiaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class KimiaController extends Controller
{
    public function getVoucherBalance($id)
    {
        $metalTrader = auth()->user();

        $cacheKey = "aggregated_view_of_invoices_{$metalTrader->id}";

        $aggregated = Cache::rememberForever($cacheKey, function () use ($metalTrader) {
            return  $metalTrader->aggregated_view_of_invoices;
        });

        $balance = KimiaService::getVoucherBalance($id, $aggregated);

        return response()->json($balance);
    }

    public function getTransactions()
    {
        $metalTrader = Auth::guard('metal-trader-api')->user();

        $startDate = request()->input('start', now());
        $endDate   = request()->input('end', now()->subMonth());
        $pageSize  = request()->input('pageSize', 100000000);

        $startDate = Carbon::parse($startDate)->format('Y-m-d');
        $endDate   = Carbon::parse($endDate)->format('Y-m-d');

        $walletIds = MetalTraderWallet::where('metal_trader_id', $metalTrader->id)->get()->pluck('id')->toArray();

        $transactions = WalletTransaction::query()
            ->join('metal_orders', 'metal_orders.id', '=', 'wallet_transactions.metal_order_id')
            ->join('metal_items', 'metal_items.id', '=', 'metal_orders.metal_item_id')
            ->whereIn('wallet_transactions.metal_trader_wallet_id', $walletIds)
            ->select([
                'wallet_transactions.*', // یا ترجیحاً فقط فیلدهای مورد نیازت از تراکنش
                'metal_items.title as metal_item_title',
                'metal_items.unit as metal_item_unit',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

//        $transactions = KimiaService::getVoucherTransactions($metalTrader->kimi_account_id, [
//            'id'         => $metalTrader->kimi_account_id,
//            'fromDate'   => $startDate,
//            'toDate'     => $endDate,
//            'pageNumber' => null,
//            'pageSize'   => $pageSize,
//            'descending' => null,
//        ]);

        return response()->json($transactions);
    }


    public function kimia()
    {
//        $data = KimiaService::voucherExchangeGold([
//            'RequestId' => Str::uuid()->toString(),
//            'AddToExistingDateVoucher' => false,
//            'AccountId' => 3564,
//            'Date' => null,
//            'Comment' => 'معامله آنلاین',
//            'Action' => 32,
//            'CurrencyId' => null,
//            'GoldPrice' => 453000000,
//            'GoldUnit' => null,
//            'Value' => 1 // گرم
//        ]);

//        $data = KimiaService::voucherExchangeMoney([
//            'RequestId' => Str::uuid()->toString(),
//            'AddToExistingDateVoucher' => false,
//            'AccountId' => 3564,
//            'Date' => null,
//            'Comment' => 'معامله آنلاین',
//            'Action' => 64,
//            'CurrencyId' => 11, // ۱۱ ریال |
//            'GoldPrice' => 453000000,
//            'GoldUnit' => null,
//            'Value' => 50000000 // ریال
//        ]);

//        $data = KimiaService::voucherExchangeCurrency([
//            'RequestId' => Str::uuid()->toString(),
//            'AddToExistingDateVoucher' => false,
//            'AccountId' => 3564,
//            'Date' => null,
//            'Comment' => 'معامله آنلاین',
//            'Action' => 64, // 32 buy | 64 sell
//            'SourceId' => null, // شناسه ارز یا سکه مبدا
//            'TargetId' => null, // شناسه ارز یا سکه مقصد
//            'UnitPrice' => null, // فی
//            'DivideUnitPrice' => null, // مقصد گران تر از مبدا است؟ boolean - nullable
//            'Quantity' => null, // تعداد
//            'GoldPrice' => 453000000, // (قیمت طلا (⚠️ فقط برای سکه غیره
//            'GoldUnit' => null, // (واحد طلا (⚠️ فقط برای سکه غیره
//        ]);

//        $data = KimiaService::getVoucherBalances();
//        $data = KimiaService::getVoucherBalance(3564);
        $data = KimiaService::getVoucherTransactions(3564, []);
//        $data = KimiaService::getProductCurrencies();
//        $data = KimiaService::getProductCoins();
//        $data = KimiaService::getProduct();
//        $data = KimiaService::getAccounts([
//            'AccountId' => 794,
//            'AccountCode' => null,
//            'Name' => null,
//            'NationalCode' => null,
//            'ShopName' => null,
//            'EconomicCode' => null,
//            'Tel' => null,
//            'Mobile' => null,
//            'PostalCode' => null,
//            'Address' => null,
//            'DateBirthday' => null,
//            'Comment' => null,
//            'Type' => null, // 1 = بنکداری 3 = تکفروشی 5 = سرمایه و برداشت 6 = بانک 8 = حساب‌داخلی 9 = ذوب 10 = امانات 11 = هزینه
//            'IsVisible' => null,
//            'onlyCheckExists' => null,
//        ]);

//        3564 و 3361

//        $data = KimiaService::getAccounts(['AccountId' => 3564]);

//        $data = KimiaService::getVoucherTransactions(2896, [
//            'id' => 2896,
//            'fromDate' => Jalalian::fromFormat('Y/m/d', '1404/07/01')->toCarbon()->format('Y-m-d'),
//            'toDate' => Jalalian::fromFormat('Y/m/d', '1404/07/15')->toCarbon()->format('Y-m-d'),
//            'pageNumber' => null, // integer default null
//            'pageSize' => null, // integer default null
//            'descending' => null, // boolean default null
//        ]);

        return $data;
    }


}
