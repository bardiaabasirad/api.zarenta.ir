<?php

use App\Http\Controllers\api\v1\Providers\HamtalaOrderCallbackController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/providers')->group(function () {
    Route::post('/hamtala/order-callback', [HamtalaOrderCallbackController::class, 'handle'])->withoutMiddleware([App\Http\Middleware\VerifyCsrfToken::class]);
});
