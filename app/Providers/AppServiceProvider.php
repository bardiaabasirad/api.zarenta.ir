<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\BoardCoin;
use App\Models\Contact;
use App\Models\MetalItem;
use App\Models\MetalTrader;
use App\Models\MetalOrder;
use App\Models\MetalOrderExchange;
use App\Models\MarketPrice;
use App\Models\Order;
use App\Models\SelectedMetalPrice;
use App\Models\PriceSourceMapping;
use App\Models\Setting;
use App\Models\RawMetalPrice;
use App\Models\User;
use App\Observers\BoardCoinObserver;
use App\Observers\ContactObserver;
use App\Observers\MetalItemObserver;
use App\Observers\MetalTraderObserver;
use App\Observers\MetalOrderExchangeObserver;
use App\Observers\MetalOrderObserver;
use App\Observers\MarketPriceObserver;
use App\Observers\SelectedMetalPriceObserver;
use App\Observers\PriceSourceMappingObserver;
use App\Observers\SettingObserver;
use App\Observers\RawMetalPriceObserver;
use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PriceSourceMapping::observe(PriceSourceMappingObserver::class);
        RawMetalPrice::observe(RawMetalPriceObserver::class);
        SelectedMetalPrice::observe(SelectedMetalPriceObserver::class);
        MarketPrice::observe(MarketPriceObserver::class);
        MetalOrder::observe(MetalOrderObserver::class);
        Setting::observe(SettingObserver::class);
        MetalOrderExchange::observe(MetalOrderExchangeObserver::class);
        User::observe(UserObserver::class);
        MetalTrader::observe(MetalTraderObserver::class);
        MetalItem::observe(MetalItemObserver::class);
        Contact::observe(ContactObserver::class);
        BoardCoin::observe(BoardCoinObserver::class);

        Relation::morphMap([
            'metal_trader' => MetalTrader::class,
            'order' => Order::class,
            'admin' => Admin::class,
        ]);

        Validator::extend('iban', function ($attribute, $value, $parameters, $validator) {
            $iban = Str::upper(str_replace(' ', '', $value));

            // If user didn't include "IR", prepend it
            if (!Str::startsWith($iban, 'IR')) {
                $iban = 'IR' . $iban;
            }

            $countryCode = substr($iban, 0, 2);
            $ibanLength = strlen($iban);

            $countryFormat = [
                'IR' => 26,
            ];

            if (isset($countryFormat[$countryCode])) {
                return $ibanLength === $countryFormat[$countryCode]
                    && preg_match('/^[A-Z0-9]+$/', substr($iban, 4));
            }

            return false;
        });


        Validator::extend('card_number', function ($attribute, $value, $parameters, $validator) {
            $cardNumber = preg_replace('/\D/', '', $value); // Remove non-digits

            // Check if it's exactly 16 digits
            if (strlen($cardNumber) !== 16) {
                return false;
            }

            // Check if all characters are digits
            return preg_match('/^\d{16}$/', $cardNumber);
        });

        Validator::extend('iban_or_card', function ($attribute, $value, $parameters, $validator) {
            // Check if it's a valid IBAN
            $ibanValidator = Validator::make([$attribute => $value], [$attribute => 'iban']);
            if (!$ibanValidator->fails()) {
                return true;
            }

            // Check if it's a valid card number
            $cardValidator = Validator::make([$attribute => $value], [$attribute => 'card_number']);
            if (!$cardValidator->fails()) {
                return true;
            }

            return false;
        });

    }

}
