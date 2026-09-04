<?php

namespace App\Http\Controllers\api\v1\general;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    public function contactInfo()
    {
        $contactInfo = Cache::remember('contact_info', 60*24*30, function () {
            return Setting::whereIn('option_key', [
                'support_center_contact_number',
                'support_contact_number',
                'address',
                'eitaa_channel',
                'instagram_channel'
            ])->get(['id','option_key','option_value']);
        });

        $contactPage = Cache::remember('contact_page', 60*24*30, function () {
            return Page::where('slug','contact')->firstOrFail();
        });

        return response()->json([
            'contact_info' => $contactInfo,
            'contact_page' => $contactPage,
        ]);
    }

    public function vat()
    {
        $vat = Setting::where('option_key', 'value_added_tax')->first();

        return response()->json($vat->option_value);
    }
}
