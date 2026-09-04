<?php

namespace App\Http\Controllers;

use App\Services\KimiaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Response;

class PdfController extends Controller
{
    public function generateClientTransactionPDF()
    {
        $client = Auth::guard('metal-trader-api')->user();

        $voucherBalances = KimiaService::getVoucherBalance($client->kimi_account_id, $client->id);

        $voucherBalances = is_array($voucherBalances) ? $voucherBalances : $voucherBalances->toArray();

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

        $html = view('pdf.transactions', compact(['transactions', 'voucherBalances', 'startDate', 'endDate']))->render();

        if(App::environment('production')) {
            $pdf = Browsershot::html($html)
                ->showBackground()
                ->margins(4, 4, 4, 4)
                ->format('A4')
                ->landscape()
                ->waitUntilNetworkIdle()
                ->setChromePath('/usr/bin/google-chrome')
                ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
                ->ignoreHttpsErrors()
                ->pdf();
        }
        else {
            $pdf = Browsershot::html($html)
                ->showBackground()
                ->margins(4, 4, 4, 4)
                ->format('A4')
                ->landscape()
                ->waitUntilNetworkIdle()
                ->setChromePath('C:\Program Files\Google\Chrome\Application\chrome.exe')
                ->ignoreHttpsErrors()
                ->pdf();
        }

        return Response::make($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="zhik-' . jalali($startDate) .'-'. jalali($endDate) . '.pdf"',
        ]);
    }
}
