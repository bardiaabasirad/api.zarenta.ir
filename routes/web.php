<?php

use App\Http\Controllers\api\v1\general\CartController;
use App\Jobs\CheckingMetalOrderExpiration;
use Illuminate\Support\Facades\Route;

Route::post('checkout/confirmation', [CartController::class, 'confirmation'])
    ->withoutMiddleware([App\Http\Middleware\VerifyCsrfToken::class])->name('jibit.payment.callback');

Route::get('checkout/azki/confirmation', [CartController::class, 'azkiConfirmation'])
    ->withoutMiddleware([App\Http\Middleware\VerifyCsrfToken::class]);

Route::get('market_price', [\App\Http\Controllers\ManualController::class, 'getMarketPrice']);

Route::prefix('test')->group(function () {
    Route::get('', [\App\Http\Controllers\ManualController::class, 'test']);
    Route::get('/melted-order-expiration', function () {
        (new CheckingMetalOrderExpiration)->handle();
    });
    Route::get('refresh-jibit-token', [\App\Http\Controllers\ManualController::class, 'refreshJibitToken']);
});

Route::prefix('hamtala')->group(function () {
    Route::get('get-latest-prices', [\App\Http\Controllers\HamtalaController::class, 'getLatestPrices']);
    Route::get('order-status', [\App\Http\Controllers\HamtalaController::class, 'getOrderStatus']);
    Route::get('get-product-price/{product_id}', [\App\Http\Controllers\HamtalaController::class, 'getProductPrice']);
    Route::get('list-product-cached', [\App\Http\Controllers\HamtalaController::class, 'listProductCached']);
    Route::get('list-product', [\App\Http\Controllers\HamtalaController::class, 'listProduct']);
});
