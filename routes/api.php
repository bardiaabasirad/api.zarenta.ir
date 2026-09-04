<?php

use App\Http\Controllers\api\MetalPriceController;
use App\Http\Controllers\api\v1\admin\MetalItemController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/api.admin.php';
require __DIR__.'/api.general.php';
require __DIR__.'/api.external.php';
require __DIR__ . '/api.traders.php';
require __DIR__ . '/api.providers.php';
require __DIR__ . '/api.test.php';

Route::middleware(['verify.secret'])->group(function () {
    Route::post('new-rate', [MetalPriceController::class, 'newPrice']);
    Route::get('metal-item-mappings/{priceSource}', [MetalItemController::class, 'getMappings']);
});

Route::get('/ping', fn() => response()->json(['ok' => true]))->middleware('iran.only');

//Route::post('/rate', [\App\Http\Controllers\ManualController::class, 'postTest'])->withoutMiddleware([App\Http\Middleware\VerifyCsrfToken::class]);

// Health check endpoint for connectivity testing
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
    ]);
});
