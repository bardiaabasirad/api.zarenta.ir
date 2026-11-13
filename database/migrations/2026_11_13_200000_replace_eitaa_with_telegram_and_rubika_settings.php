<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        $eitaa = Setting::query()->where('option_key', 'eitaa_channel')->first();

        if ($eitaa) {
            $eitaa->option_key = 'telegram_channel';
            $eitaa->option_name = 'آدرس پیج تلگرام';
            $eitaa->save();
        } else {
            $telegram = Setting::query()->firstOrNew(['option_key' => 'telegram_channel']);
            $telegram->option_name = 'آدرس پیج تلگرام';
            $telegram->option_value = $telegram->exists ? ($telegram->option_value ?? '') : '';
            $telegram->save();
        }

        $instagram = Setting::query()->where('option_key', 'instagram_channel')->first();
        if ($instagram) {
            $instagram->option_name = 'آدرس پیج اینستاگرام';
            $instagram->save();
        }

        $rubika = Setting::query()->firstOrNew(['option_key' => 'rubika_channel']);
        $rubika->option_name = 'آدرس پیج روبیکا';
        $rubika->option_value = $rubika->exists ? ($rubika->option_value ?? '') : '';
        $rubika->save();

        Cache::forget('contact_info');
    }

    public function down(): void
    {
        $telegram = Setting::query()->where('option_key', 'telegram_channel')->first();

        if ($telegram) {
            $telegram->option_key = 'eitaa_channel';
            $telegram->option_name = 'کانال ایتا';
            $telegram->save();
        }

        Setting::query()->where('option_key', 'rubika_channel')->delete();

        $instagram = Setting::query()->where('option_key', 'instagram_channel')->first();
        if ($instagram) {
            $instagram->option_name = 'کانال اینستاگرام';
            $instagram->save();
        }

        Cache::forget('contact_info');
    }
};
