<?php

use App\Http\Controllers\api\v1\Providers\HamtalaOrderCallbackController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/test')->group(function () {
    Route::post('taban', [\App\Http\Controllers\ManualController::class, 'setNewTaban']);
})->middleware('iran.only');
