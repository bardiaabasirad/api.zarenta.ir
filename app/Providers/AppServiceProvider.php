<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Banner;
use App\Models\Category;
use App\Models\MetalTrader;
use App\Models\Order;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        Relation::morphMap([
            'admin' => Admin::class,
            'metal_trader' => MetalTrader::class,
            'user' => User::class,
            'order' => Order::class,
            'widget' => Widget::class,
            'banner' => Banner::class,
            'category' => Category::class,
        ]);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
