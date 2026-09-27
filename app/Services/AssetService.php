<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AssetService
{
    public static function updateAsset($metalOrder)
    {
        Log::info('metalOrder', $metalOrder->toArray());
    }
}
