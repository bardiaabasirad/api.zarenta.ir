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
                'instagram_channel',
                'telegram_channel',
                'rubika_channel',
            ])->get(['id','option_key','option_value']);
        });

        $aboutPage = Cache::remember('about_page', 60*24*30, function () {
            return Page::where('slug', 'about')->firstOrFail();
        });

        return response()->json([
            'contact_info' => $contactInfo,
            'about_page' => $aboutPage,
        ]);
    }

    public function vat()
    {
        $vat = Setting::where('option_key', 'value_added_tax')->first();

        return response()->json($vat->option_value);
    }
}
