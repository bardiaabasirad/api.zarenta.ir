<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class LimitNationalCodeValidation
{
    public function handle($request, Closure $next)
    {
        $ipAddress = $request->ip();
        $userAgent = $request->header('User-Agent');
        $cacheKey = 'national_code_validation_' . md5($ipAddress . $userAgent);
        $maxAttempts = 3;
        $decayMinutes = 60;

        // Attempt to add the cache key with an initial value of 1 and expiration time
        if (!Cache::has($cacheKey)) {
            Cache::put($cacheKey, 1, $decayMinutes * 60);
        } else {
            $attempts = Cache::increment($cacheKey);

            if ($attempts > $maxAttempts) {
                return response()->json([
                    'errors' => [
                        'server_error' => ['محدودیت استعلام کد ملی، لطفا در ساعات آینده تلاش کنید.']
                    ]
                ], 422);
            }
        }

        return $next($request);
    }
}
