<?php

namespace App\Observers;

use App\Events\MarketStatusChanged;
use App\Events\SettingsChanged;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingObserver
{
    /**
     * Handle the Setting "updated" event.
     */
    public function saved(Setting $setting): void
    {
        // لیست کلیدهایی که کش contact_info بهشون وابسته هست
        $contactInfoKeys = [
            'support_center_contact_number',
            'support_contact_number',
            'address',
            'eitaa_channel',
            'instagram_channel'
        ];

        if (in_array($setting->option_key, $contactInfoKeys)) {
            Cache::forget('contact_info');
        }

        if (
            $setting->option_key == 'melted_stock_quantity' ||
            $setting->option_key == 'min_melted_stock_quantity' ||
            $setting->option_key == 'max_melted_stock_quantity'
        ) {
            SettingsChanged::dispatch();
        }

        if ($setting->option_key == 'market_status') {
            Cache::forget('market_status');
            event(new MarketStatusChanged($setting));
        } elseif ($setting->option_key == 'auto_order_dispatch_enabled') {
            Cache::forget('settings.auto_order_dispatch_enabled');
            SettingsChanged::dispatch();
        } elseif ($setting->option_key == 'validity_period_of_melted_order_before_expires') {
            Cache::forget('setting:validity_period_of_melted_order_before_expires');
        } elseif ($setting->option_key == 'submit_outbound_gold_orders_by_bot') {
            Cache::forget('settings:submit_outbound_gold_orders_by_bot');
        } elseif ($setting->option_key == 'manual_order_review_duration_seconds') {
            Cache::forget('settings:manual_order_review_duration_seconds');
        }
    }
}
