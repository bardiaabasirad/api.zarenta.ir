<?php

namespace App\Http\Controllers;

use App\Models\MetalTraderWallet;
use App\Models\WalletTransaction;
use App\Services\TraderTransactionPdfService;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;

class PdfController extends Controller
{
    public function generateClientTransactionPDF(Request $request, TraderTransactionPdfService $pdfService)
    {
        $metalTrader = Auth::guard('metal-trader-api')->user();

        return $pdfService->downloadPdf(
            metalTrader: $metalTrader,
            start:       $request->input('start'),
            end:         $request->input('end'),
            pageSize:    (int) $request->input('pageSize', 100)
        );
    }
}
