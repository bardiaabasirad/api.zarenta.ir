<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PriceRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiClient = $request->attributes->get('api_client');

        // Skip if no API client is present
        if (!$apiClient) {
            return $next($request);
        }

        // Fetch max allowed API calls from settings
        $maxPriceApiCalls = Setting::where('option_key', 'max_price_api_calls')->first();

        if (!$maxPriceApiCalls || !isset($maxPriceApiCalls->option_value)) {
            return response()->json([
                'status' => 'error',
                'message' => 'API configuration error. Please contact support.',
            ], 500);
        }

        // Create a cache key scoped to the client and current day
        $currentDay = now()->format('Y-m-d');
        $rateLimitKey = "price_requests:{$apiClient->id}:{$currentDay}";

        // Get current request count from cache (default to 0)
        $requests = Cache::get($rateLimitKey, 0);

        // Check if the request limit is exceeded
        if ($requests >= $maxPriceApiCalls->option_value) {
            return response()->json([
                'status' => 'rate_limit_exceeded',
                'message' => 'شما به سقف درخواست‌های روزانه رسیده‌اید',
                'current_requests' => $requests,
                'max_requests' => $maxPriceApiCalls->option_value,
            ], 429);
        }

        // Increment the counter, set to expire at the start of the next day
        $secondsUntilMidnight = now()->diffInSeconds(now()->addDay()->startOfDay());
        Cache::put($rateLimitKey, $requests + 1, $secondsUntilMidnight);

        // Add rate limit headers to the response
        $response = $next($request);
        $response->headers->set('X-SelectedMetalPrice-Limit-Limit', $maxPriceApiCalls->option_value);
        $response->headers->set('X-SelectedMetalPrice-Limit-Remaining', $maxPriceApiCalls->option_value - ($requests + 1));

        return $response;
    }
}
