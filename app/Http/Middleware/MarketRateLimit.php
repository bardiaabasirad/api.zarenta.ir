<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class MarketRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $metalTrader = $request->attributes->get('api_client');

        // Skip if no API client is present
        if (!$metalTrader) {
            return $next($request);
        }

        // Fetch max allowed API calls from settings
        $maxMarketApiCalls = Setting::where('option_key', 'max_market_api_calls')->first();

        if (!$maxMarketApiCalls || !isset($maxMarketApiCalls->option_value)) {
            return response()->json([
                'status' => 'error',
                'message' => 'API configuration error. Please contact support.',
            ], 500);
        }

        // Create a cache key scoped to the client and current day
        $currentDay = now()->format('Y-m-d');
        $rateLimitKey = "market_requests:{$metalTrader->id}:{$currentDay}";

        // Get current request count from cache (default to 0)
        $requests = Cache::get($rateLimitKey, 0);

        // Check if the request limit is exceeded
        if ($requests >= $maxMarketApiCalls->option_value) {
            return response()->json([
                'status' => 'rate_limit_exceeded',
                'message' => 'شما به سقف درخواست‌های روزانه رسیده‌اید', // Consistent with previous middleware
                'current_requests' => $requests,
                'max_requests' => $maxMarketApiCalls->option_value,
            ], 429);
        }

        // Increment the counter, set to expire at the start of the next day
        $secondsUntilMidnight = now()->diffInSeconds(now()->addDay()->startOfDay());
        Cache::put($rateLimitKey, $requests + 1, $secondsUntilMidnight);

        // Add rate limit headers to the response
        $response = $next($request);
        $response->headers->set('X-SelectedMetalPrice-Limit-Limit', $maxMarketApiCalls->option_value);
        $response->headers->set('X-SelectedMetalPrice-Limit-Remaining', $maxMarketApiCalls->option_value - ($requests + 1));

        return $response;
    }
}
