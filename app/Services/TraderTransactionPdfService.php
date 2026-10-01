<?php

namespace App\Services;

use App\Models\MetalTrader;
use App\Models\MetalTraderWallet;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Spatie\Browsershot\Browsershot;

class TraderTransactionPdfService
{
    /**
     * تولید خروجی PDF تراکنش‌های تریدر به عنوان Response دانلودی
     */
    public function downloadPdf(MetalTrader $metalTrader, ?string $start = null, ?string $end = null, int $pageSize = 100): Response
    {
        // ۱. تنظیم بازه زمانی
        $startDate = Carbon::parse($start ?? now()->subMonth())->startOfDay();
        $endDate   = Carbon::parse($end ?? now())->endOfDay();

        // ۲. استخراج کیف‌پول‌های کاربر
        $walletIds = MetalTraderWallet::query()
            ->where('metal_trader_id', $metalTrader->id)
            ->pluck('id');

        // ۳. گرفتن لیست تراکنش‌ها
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
            ->orderByDesc('wallet_transactions.created_at')
            ->limit($pageSize)
            ->get();

        // ۴. رندر ویو به HTML
        $html = view('pdf.transactions', compact('transactions', 'startDate', 'endDate', 'metalTrader'))->render();

        // ۵. تنظیمات Browsershot
        $browsershot = Browsershot::html($html)
            ->showBackground()
            ->margins(4, 4, 4, 4)
            ->format('A4')
            ->landscape()
            ->waitUntilNetworkIdle()
            ->ignoreHttpsErrors();

        if (App::environment('production')) {
            $browsershot->setChromePath('/usr/bin/google-chrome')
                ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox']);
        } else {
            $browsershot->setChromePath('C:\Program Files\Google\Chrome\Application\chrome.exe');
        }

        $pdf = $browsershot->pdf();

        $fileName = 'zarenta-transactions-' . $metalTrader->id . '-' . jalali($startDate) . '-' . jalali($endDate) . '.pdf';

        return ResponseFacade::make($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
}
