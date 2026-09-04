<?php

namespace App\Http\Controllers\api\v1\Providers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HamtalaOrderCallbackController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('response', $request->all());
    }
}
