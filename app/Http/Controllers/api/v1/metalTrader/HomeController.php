<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;

class HomeController extends Controller
{
    public function __construct(private SettingsService $settings){  }

    public function siteInfo()
    {
        $site_info = $this->settings->getMany([
            'shop_title',
            'shop_tagline',
            'shop_telephone',
            'shop_instagram_channel_id',
            'shop_eitaa_channel_id',
            'shop_address',
        ]);

        return response()->json($site_info);
    }
}
