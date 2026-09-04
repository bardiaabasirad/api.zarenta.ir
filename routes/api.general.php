<?php

use App\Http\Controllers\api\v1\general\AddressController;
use App\Http\Controllers\api\v1\general\AuthenticationController;
use App\Http\Controllers\api\v1\general\CartController;
use App\Http\Controllers\api\v1\general\DirectoryController;
use App\Http\Controllers\api\v1\general\ImageInterventionController;
use App\Http\Controllers\api\v1\general\LandingPageController;
use App\Http\Controllers\api\v1\general\MarketPriceController;
use App\Http\Controllers\api\v1\general\OrderController;
use App\Http\Controllers\api\v1\general\PersianCoinController;
use App\Http\Controllers\api\v1\general\ProductController;
use App\Http\Controllers\api\v1\general\SearchController;
use App\Http\Controllers\api\v1\general\SettingController;
use App\Http\Controllers\api\v1\general\RateController;
use App\Http\Controllers\api\v1\general\TransactionController;
use App\Http\Controllers\api\v1\general\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;


Route::prefix('v1')->middleware(['user.activity'])->group(function () {

    Route::get('callback', [TransactionController::class, 'check']);

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthenticationController::class, 'login'])
            ->middleware('throttle:5,10,user-login');
        Route::post('verify', [AuthenticationController::class, 'verify'])
            ->middleware('throttle:5,10,user-verify');
        Route::post('signup', [AuthenticationController::class, 'signup'])
            ->middleware('throttle:5,10,user-signup');
        Route::post('logout', [AuthenticationController::class, 'logout'])
            ->middleware('auth:user-api');
        Route::get('info', [UserController::class, 'info'])
            ->middleware('auth:user-api');
    });

    Route::middleware(['auth:user-api'])->group(function () {

        Route::prefix('broadcasting')->group(function () {
            // Auth endpoint for channels
            Route::post('/auth', function (Request $request) {
                $user = $request->user('user-api');

                if (!$user) {
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

        Route::get('cart', [CartController::class, 'get']);
        Route::post('cart/add', [CartController::class, 'add']);
        Route::post('cart/subtract', [CartController::class, 'subtract']);
        Route::post('cart/remove', [CartController::class, 'remove']);
        // Cart
        Route::get('shopping-costs', [CartController::class, 'shoppingCosts']);
        Route::get('addresses', [AddressController::class, 'getUserAddresses']);
        Route::get('addresses/create', [AddressController::class, 'create']);
        Route::get('addresses/{address}', [AddressController::class, 'show']);
        Route::get('addresses/{address}/with-methods', [AddressController::class, 'get']);
        Route::post('addresses', [AddressController::class, 'store']);
        Route::match(['put','patch'],'addresses/{address}', [AddressController::class, 'update']);
        Route::delete('addresses/{address}', [AddressController::class, 'destroy']);
        Route::post('pay', [CartController::class, 'pay']);
        Route::get('payment/{purchase_id}', [CartController::class, 'check']);
        // Order
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{code}', [OrderController::class, 'show']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::post('orders/{order}/repayment', [OrderController::class, 'repayment']);
    });

    Route::post('check-cart', [CartController::class, 'checkCart']);
    Route::post('check-cart-with-payment-gateways', [CartController::class, 'checkCartWithPaymentGateways']);

    // images
    Route::get('images/{path}', [ImageInterventionController::class, 'show'])
        ->where('path', '.*\.(jpg|jpeg|png|gif|webp|svg)');
    // home page
    Route::get('home', [LandingPageController::class, 'home']);

    Route::get('get-aggregated-rates', [RateController::class, 'getAggregatedRates']);
    // contact information
    Route::get('contact-info', [SettingController::class, 'contactInfo']);
    // products
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    // Board
    Route::get('board-landing',[LandingPageController::class, 'board']);
    Route::get('board/coins',[LandingPageController::class, 'coins']);
    // Persian coins
    Route::get('persian-coins',[PersianCoinController::class, 'all']);
    // market price
    Route::get('market-price',[MarketPriceController::class, 'last'])->middleware('throttle:60,1,market-price');
    // search
    Route::get('autocomplete', [SearchController::class, 'autocomplete']);
    Route::get('search', [SearchController::class, 'search']);
    // directory
    Route::get('directories', [DirectoryController::class, 'products']);
    // get last price
    Route::get('domestic-market', [RateController::class, 'domesticMarket']);
    // domestic market page
    Route::get('domestic-market-landing', [RateController::class, 'domesticMarketLanding']);
    Route::get('domestic-market-landing/last-ones/{rate?}', [RateController::class, 'lastOnes']);
    // Settings
    Route::get('vat', [SettingController::class, 'vat']);
    //
    Route::get('cart-duration-validity', [CartController::class, 'cartDurationValidity']);

//        Route::prefix('azki')->group(function () {
//            Route::post('payment', [AzkiController::class, 'payment']);
//            Route::get('products', [AzkiController::class, 'products']);
//        });
});
