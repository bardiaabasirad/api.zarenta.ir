<?php

use App\Http\Controllers\api\v1\metalTrader\AuthenticationController;
use App\Http\Controllers\api\v1\metalTrader\ApiClientController;
use App\Http\Controllers\api\v1\metalTrader\ContactController;
use App\Http\Controllers\api\v1\metalTrader\HomeController;
use App\Http\Controllers\api\v1\metalTrader\InquiryController;
use App\Http\Controllers\api\v1\metalTrader\MetalTraderLeadController;
use App\Http\Controllers\api\v1\metalTrader\OrderController;
use App\Http\Controllers\api\v1\metalTrader\RateController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::prefix('v1/clients')
    ->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('otp/send', [AuthenticationController::class, 'otpSend'])
            ->middleware('throttle:5,10,metal-trader-login');
        Route::post('otp/verify', [AuthenticationController::class, 'verify'])
            ->middleware('throttle:5,10,metal-trader-verify');
        Route::post('password/login', [AuthenticationController::class, 'passwordLogin'])
            ->middleware('throttle:5,10,metal-trader-verify-otp');
        Route::patch('request-review', [AuthenticationController::class, 'submitReevaluationRequest'])
            ->middleware([
                'auth:metal-trader-api',
                'abilities:request-review',
                'throttle:5,10,metal-trader-request-review'
            ]);
        Route::post('change-password', [AuthenticationController::class, 'changePassword'])
            ->middleware([
                'auth:metal-trader-api',
                'abilities:reset-password',
                'throttle:5,10,metal-trader-change-password'
            ]);
        Route::post('complete-profile', [AuthenticationController::class, 'completeProfile']);
        Route::post('logout', [AuthenticationController::class, 'logout'])
            ->middleware('auth:metal-trader-api');
        Route::get('info', [ApiClientController::class, 'info'])
            ->middleware('auth:metal-trader-api');
    });

    Route::post('change-password', [AuthenticationController::class, 'changePasswordByProfile'])
        ->middleware([
            'auth:metal-trader-api',
            'throttle:5,10,metal-trader-change-password-by-profile'
        ]);

    Route::post('lead', [MetalTraderLeadController::class, 'store']);

    Route::get('site-info', [HomeController::class, 'siteInfo']);

    Route::middleware(['auth:metal-trader-api', 'client.activity', 'metal-trader.active'])->group(function () {

        Route::prefix('broadcasting')->group(function () {
            // Auth endpoint for channels
            Route::post('/auth', function (Request $request) {
                $metalTrader = $request->user('metal-trader-api');

                if (!$metalTrader) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }

                $channelName = $request->input('channel_name');
                $socketId = $request->input('socket_id');

                if (!$channelName || !$socketId) {
                    return response()->json(['error' => 'Missing parameters'], 400);
                }

                // استفاده از Laravel Broadcast برای authorize کردن
                return Broadcast::auth($request);
            });
        });

        Route::get('orders', [OrderController::class, 'index']);
        Route::patch('settings/market-opening-notification', [\App\Http\Controllers\api\v1\metalTrader\SettingController::class, 'updateMarketOpeningNotification']);
        Route::patch('settings/aggregated-view-of-invoices', [\App\Http\Controllers\api\v1\metalTrader\SettingController::class, 'updateAggregatedViewOfInvoices']);
        Route::get('orders/{tracking_code}', [OrderController::class, 'show']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('rate', [RateController::class, 'getRate']);
        Route::get('balance/{id}', [\App\Http\Controllers\api\v1\admin\KimiaController::class, 'getVoucherBalance']);
        Route::get('transactions', [\App\Http\Controllers\api\v1\admin\KimiaController::class, 'getTransactions']);
        Route::get('transactions/pdf', [\App\Http\Controllers\PdfController::class, 'generateClientTransactionPDF']);
        Route::get('contacts', [ContactController::class, 'index']);

        // inquiries
        Route::prefix('inquiries')->group(function () {
            Route::get('fee', [InquiryController::class, 'fee']);
            Route::post('matching', [InquiryController::class, 'matching']);
            Route::post('similarity', [InquiryController::class, 'similarity']);
            Route::post('iban', [InquiryController::class, 'iban']);
            Route::post('iban-from-card', [InquiryController::class, 'ibanFromCard']);
        });
    });
});
