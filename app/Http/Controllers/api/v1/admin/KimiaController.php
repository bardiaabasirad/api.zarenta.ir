<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
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
        $client = Auth::guard('metal-trader-api')->user();

        $startDate = request()->input('start', now());
        $endDate   = request()->input('end', now()->subMonth());
        $pageSize  = request()->input('pageSize', 100000000);

        $startDate = Carbon::parse($startDate)->format('Y-m-d');
        $endDate   = Carbon::parse($endDate)->format('Y-m-d');

        $transactions = KimiaService::getVoucherTransactions($client->kimi_account_id, [
            'id'         => $client->kimi_account_id,
            'fromDate'   => $startDate,
            'toDate'     => $endDate,
            'pageNumber' => null,
            'pageSize'   => $pageSize,
            'descending' => null,
        ]);

        // فیلتر کردن آیتم‌هایی که هر دو مقدار صفر دارند
        if (isset($transactions['Items']) && is_array($transactions['Items'])) {
            $transactions['Items'] = collect($transactions['Items'])
                ->filter(function ($item) {
                    $removeByWeightAndMoneyZero = (
                        isset($item['CumulativeWeight750'], $item['CumulativeSumMoney']) &&
                        $item['CumulativeWeight750'] == 0 &&
                        $item['CumulativeSumMoney'] == 0
                    );

                    $removeByRecordIdAndMoneyZero = (
                        isset($item['RecordId'], $item['CumulativeSumMoney']) &&
                        $item['RecordId'] == -1 &&
                        $item['CumulativeSumMoney'] == 0
                    );

                    // حذف کن اگه یکی از شرط‌ها برقرار باشه
                    return !($removeByWeightAndMoneyZero || $removeByRecordIdAndMoneyZero);
                })
                ->values()
                ->all();
        }

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
