<?php

use App\Http\Controllers\api\v1\external\ClientMarketController;
use App\Http\Controllers\api\v1\external\ClientOrderController;
use App\Http\Controllers\api\v1\external\ServiceController;
use Illuminate\Support\Facades\Route;

// For our another system
// برای سیستم طلاپیما محصولات از اینجا خوانده میشه
//Route::middleware(['verify.api.keys'])->group(function () {
//    Route::get('/products', [ProductController::class, 'home']);
//    Route::get('/market', [MarketController::class, 'market']);
//    Route::get('/market-comparison', [MarketController::class, 'marketComparison']);
//});

// For clients
Route::middleware(['verify.client.api.key'])->group(function () {

    Route::middleware(['has.feature:inquiry', 'subscription.not.expired:inquiry'])->group(function () {
        Route::prefix('services')->group(function () {
            Route::post('matching', [ServiceController::class, 'matching']);
            Route::post('similarity', [ServiceController::class, 'similarity']);
            Route::post('iban-from-card', [ServiceController::class, 'ibanFromCard']);
        });
    });

    Route::middleware(['has.feature:order_create', 'subscription.not.expired:order_create'])->group(function () {
        Route::get('/clients/orders', [ClientOrderController::class, 'index']);
        Route::get('/clients/orders/track', [ClientOrderController::class, 'track']);
        Route::post('/clients/orders', [ClientOrderController::class, 'store']);
    });

    Route::middleware(['has.feature:get_price', 'subscription.not.expired:get_price', 'price.rate.limit'])->group(function () {
        Route::get('/clients/price', [ClientMarketController::class, 'price']);
    });

    Route::middleware(['has.feature:get_market', 'subscription.not.expired:get_market', 'market.rate.limit'])->group(function () {
        Route::get('/clients/market', [ClientMarketController::class, 'market']);
    });
});
