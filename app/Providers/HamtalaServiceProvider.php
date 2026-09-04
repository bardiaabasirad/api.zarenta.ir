<?php

namespace App\Providers;

use App\Services\Hamtala\HamtalaClient;
use Illuminate\Support\ServiceProvider;

class HamtalaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HamtalaClient::class, fn () => new HamtalaClient(
            config('services.hamtala.base_url'),
            config('services.hamtala.app_key'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
